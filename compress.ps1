$sourceDir = "c:\laragon\www\sihemat"
$outZip = "c:\laragon\www\sihemat\sihemat-deploy.zip"

if (Test-Path $outZip) { Remove-Item $outZip }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Ambil semua file kecuali .git, node_modules, dan file zip ini sendiri
$files = Get-ChildItem -Path $sourceDir -Recurse -File | Where-Object {
    $_.FullName -notmatch '\\\.git\\' -and
    $_.FullName -notmatch '\\node_modules\\' -and
    $_.FullName -notmatch '\\sihemat-deploy\.zip$'
}

$totalCount = $files.Count
$zip = [System.IO.Compression.ZipFile]::Open($outZip, [System.IO.Compression.ZipArchiveMode]::Create)

$lastPercent = -1

Write-Output "Memulai kompresi $totalCount file..."

for ($i = 0; $i -lt $totalCount; $i++) {
    $file = $files[$i]
    $relPath = $file.FullName.Substring($sourceDir.Length + 1)
    
    try {
        $null = [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $relPath)
    } catch {
        # Lewati file jika tidak bisa dibaca (sedang digunakan)
    }
    
    $percent = [math]::Floor(($i + 1) / $totalCount * 100)
    if ($percent % 5 -eq 0 -and $percent -ne $lastPercent) {
        Write-Output "Progress kompresi: $percent%"
        $lastPercent = $percent
    }
}

$zip.Dispose()
Write-Output "Kompresi selesai! File berhasil disimpan di $outZip"
