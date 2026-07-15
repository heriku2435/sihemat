import makeWASocket, { useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } from '@whiskeysockets/baileys';
import pino from 'pino';
import express from 'express';
import cors from 'cors';
import qrcodeTerminal from 'qrcode-terminal';
import qrcode from 'qrcode';

const app = express();
app.use(cors());
app.use(express.json());

let sock;
let currentStatus = 'disconnected'; // 'disconnected', 'qr', 'connected'
let currentQr = null; // Base64 QR code

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`Using WA v${version.join('.')}, isLatest: ${isLatest}`);
    
    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' })
    });

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        if (qr) {
            currentStatus = 'qr';
            console.log('New QR code received');
            qrcodeTerminal.generate(qr, { small: true });
            
            try {
                currentQr = await qrcode.toDataURL(qr);
            } catch (err) {
                console.error('Error generating QR code image', err);
            }
        }
        
        if (connection === 'close') {
            currentStatus = 'disconnected';
            currentQr = null;
            const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log('Connection closed due to ', lastDisconnect?.error, ', reconnecting ', shouldReconnect);
            
            if (shouldReconnect) {
                connectToWhatsApp();
            } else {
                console.log('Logged out. Please delete auth_info_baileys to scan again.');
            }
        } else if (connection === 'open') {
            currentStatus = 'connected';
            currentQr = null;
            console.log('WhatsApp connection opened successfully!');
        }
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type === 'notify') {
            for (let msg of messages) {
                // Ignore message from self or group
                if (!msg.key.fromMe && msg.message && msg.key.remoteJid.endsWith('@s.whatsapp.net')) {
                    try {
                        const replyMessage = "_(Auto Reply)_\n\nMohon maaf, nomor ini hanya digunakan oleh Sistem untuk mengirimkan notifikasi. Kami tidak dapat menerima/menjawab pesan balasan Anda.\n\nSilakan hubungi pihak Sekolah atau Guru terkait jika ada pertanyaan.";
                        await sock.sendMessage(msg.key.remoteJid, { text: replyMessage }, { quoted: msg });
                    } catch (error) {
                        console.error('Error sending auto-reply:', error);
                    }
                }
            }
        }
    });
}

// Start connection
connectToWhatsApp();

// API endpoint to check status
app.get('/status', (req, res) => {
    return res.status(200).json({
        status: currentStatus,
        qr: currentQr
    });
});

// API endpoint to send message
app.post('/send-message', async (req, res) => {
    let { number, message } = req.body;

    if (!number || !message) {
        return res.status(400).json({ status: false, message: 'Number and message are required' });
    }

    if (currentStatus !== 'connected') {
        return res.status(503).json({ status: false, message: 'WhatsApp Gateway is not connected' });
    }

    number = number.toString().replace(/[^0-9]/g, '');
    if (number.startsWith('0')) {
        number = '62' + number.substring(1);
    } else if (number.startsWith('+')) {
        number = number.substring(1);
    }
    
    if (!number.endsWith('@s.whatsapp.net')) {
        number = number + '@s.whatsapp.net';
    }

    try {
        const [result] = await sock.onWhatsApp(number);
        if (result && result.exists) {
            await sock.sendMessage(number, { text: message });
            return res.status(200).json({ status: true, message: 'Message sent successfully' });
        } else {
            return res.status(404).json({ status: false, message: 'WhatsApp number not registered' });
        }
    } catch (error) {
        console.error('Error sending message:', error);
        return res.status(500).json({ status: false, message: 'Failed to send message', error: error.toString() });
    }
});

// API endpoint to shutdown server
app.post('/shutdown', (req, res) => {
    console.log('Shutdown requested via API');
    res.status(200).json({ status: true, message: 'Shutting down server...' });
    setTimeout(() => {
        process.exit(0);
    }, 1000);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`WhatsApp Gateway is running on port ${PORT}`);
});
