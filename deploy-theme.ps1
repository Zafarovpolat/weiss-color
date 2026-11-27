# Выгрузка темы weiss-color на стенд по FTP.
# Доступы берутся из .ftp.env рядом со скриптом или в каталоге выше (вне git):
#   FTP_HOST=rusoagency.beget.tech
#   FTP_USER=...
#   FTP_PASS=...
#   FTP_DIR=/weisscolor.ruso.agency/public_html/wp-content/themes/weiss-color
#
# По умолчанию выгружаются только код и стили (*.php, *.css, *.js).
# Ключ -All — вся тема целиком, включая изображения.
# Ключ -Prune — удалить на сервере файлы js/ и css/, которых больше нет
# в теме: иначе убранные файлы остаются открытыми по адресу (замечание 97).

param(
    [switch]$All,
    [switch]$WhatIf,
    [switch]$Backup,
    [switch]$Prune,
    [string[]]$Extra = @(),
    [string[]]$Only = @()
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$theme = Join-Path $root '03_wp\wp-content\themes\weiss-color'
$envFile = Join-Path $root '.ftp.env'
if (-not (Test-Path $envFile)) {
    $envFile = Join-Path (Split-Path -Parent $root) '.ftp.env'
}

if (-not (Test-Path $theme)) { throw "Theme not found: $theme" }

$cfg = @{}
if (Test-Path $envFile) {
    foreach ($line in Get-Content -LiteralPath $envFile -Encoding UTF8) {
        if ($line -match '^\s*#' -or $line -notmatch '=') { continue }
        $k, $v = $line -split '=', 2
        $cfg[$k.Trim()] = $v.Trim()
    }
}

foreach ($key in 'FTP_HOST', 'FTP_USER', 'FTP_PASS', 'FTP_DIR') {
    if (-not $WhatIf -and -not $cfg[$key]) { throw "Missing $key in .ftp.env" }
}

$hostName = if ($cfg['FTP_HOST']) { $cfg['FTP_HOST'] } else { 'example.invalid' }
$user = $cfg['FTP_USER']
$pass = $cfg['FTP_PASS']
$remoteBase = if ($cfg['FTP_DIR']) { $cfg['FTP_DIR'].TrimEnd('/') } else { '/theme' }

$extensions = if ($All) { @() } else { @('.php', '.css', '.js') }
$extraSet = @{}
foreach ($item in $Extra) { $extraSet[($item -replace '/', '\')] = $true }

$files = @(Get-ChildItem -LiteralPath $theme -Recurse -File | Where-Object {
    if ($All) { $true }
    elseif ($extensions -contains $_.Extension.ToLower()) { $true }
    else { $extraSet.ContainsKey($_.FullName.Substring($theme.Length + 1)) }
})

# -Only — выгрузить только перечисленные файлы (пути от корня темы).
if ($Only.Count -gt 0) {
    $onlySet = @{}
    foreach ($item in $Only) { $onlySet[($item -replace '/', '\')] = $true }
    $files = @($files | Where-Object { $onlySet.ContainsKey($_.FullName.Substring($theme.Length + 1)) })
    if ($files.Count -eq 0) { throw "No files match -Only: $($Only -join ', ')" }
}

Write-Host ("Theme: {0}" -f $theme)
Write-Host ("Remote: ftp://{0}{1}" -f $hostName, $remoteBase)
Write-Host ("Files: {0} ({1:N1} MB)" -f $files.Count, (($files | Measure-Object Length -Sum).Sum / 1MB))

if ($WhatIf) {
    $files | ForEach-Object { $_.FullName.Substring($theme.Length + 1) }
    return
}

$cred = New-Object System.Net.NetworkCredential($user, $pass)
$madeDirs = @{}

function Ensure-RemoteDir([string]$relativeDir) {
    if (-not $relativeDir -or $madeDirs.ContainsKey($relativeDir)) { return }
    $parts = $relativeDir -split '[\\/]'
    $path = $remoteBase
    foreach ($part in $parts) {
        $path = "$path/$part"
        if ($madeDirs.ContainsKey($path)) { continue }
        try {
            $req = [System.Net.FtpWebRequest]::Create("ftp://$hostName$path")
            $req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
            $req.Credentials = $cred
            $req.UseBinary = $true
            $req.UsePassive = $true
            $req.KeepAlive = $false
            $req.GetResponse().Close()
        } catch {
            # каталог уже существует — это нормально
        }
        $madeDirs[$path] = $true
    }
}

$backupDir = Join-Path $env:TEMP ('weiss-theme-backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
if ($Backup) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Write-Host ("Backup: {0}" -f $backupDir)
}

function Backup-RemoteFile([string]$relative) {
    if (-not $Backup) { return }
    try {
        $req = [System.Net.FtpWebRequest]::Create("ftp://$hostName$remoteBase/$relative")
        $req.Method = [System.Net.WebRequestMethods+Ftp]::DownloadFile
        $req.Credentials = $cred
        $req.UseBinary = $true
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $res = $req.GetResponse()
        $target = Join-Path $backupDir ($relative -replace '/', '\')
        New-Item -ItemType Directory -Path (Split-Path $target -Parent) -Force | Out-Null
        $in = $res.GetResponseStream()
        $out = [System.IO.File]::Create($target)
        $in.CopyTo($out)
        $out.Close()
        $in.Close()
        $res.Close()
    } catch {
        # файла на сервере нет — бекапить нечего
    }
}

$done = 0
foreach ($file in $files) {
    $relative = $file.FullName.Substring($theme.Length + 1) -replace '\\', '/'
    $dir = Split-Path $relative -Parent
    Ensure-RemoteDir $dir

    $url = "ftp://$hostName$remoteBase/$relative"
    Backup-RemoteFile $relative

    try {
        $req = [System.Net.FtpWebRequest]::Create($url)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.Credentials = $cred
        $req.UseBinary = $true
        $req.UsePassive = $true
        $req.KeepAlive = $false
        $req.ContentLength = $file.Length
        $stream = $req.GetRequestStream()
        $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        $req.GetResponse().Close()
        $done++
        if ($done % 10 -eq 0) { Write-Host ("  {0}/{1}" -f $done, $files.Count) }
    } catch {
        Write-Host ("FAILED: {0} — {1}" -f $relative, $_.Exception.Message) -ForegroundColor Red
    }
}

Write-Host ("Uploaded {0} of {1} files." -f $done, $files.Count)

if ($Prune) {
    $local = @{}
    foreach ($file in $files) {
        $local[($file.FullName.Substring($theme.Length + 1) -replace '\\', '/')] = $true
    }

    $removed = 0
    foreach ($dirName in @('js', 'css')) {
        if (-not (Test-Path (Join-Path $theme $dirName))) { continue }

        try {
            $req = [System.Net.FtpWebRequest]::Create("ftp://$hostName$remoteBase/$dirName")
            $req.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
            $req.Credentials = $cred
            $req.UseBinary = $true
            $req.UsePassive = $true
            $req.KeepAlive = $false
            $res = $req.GetResponse()
            $reader = New-Object System.IO.StreamReader($res.GetResponseStream())
            $names = @()
            while ($null -ne ($line = $reader.ReadLine())) {
                if ($line.Trim()) { $names += $line.Trim() }
            }
            $reader.Close()
            $res.Close()
        } catch {
            Write-Host ("PRUNE SKIPPED: {0} — {1}" -f $dirName, $_.Exception.Message) -ForegroundColor Yellow
            continue
        }

        foreach ($name in $names) {
            # Beget в ListDirectory отдаёт полный путь («js/zoom-mode.js»),
            # поэтому берём только имя файла, иначе получается js/js/... и 550.
            $leaf = [System.IO.Path]::GetFileName(($name -replace '\\', '/'))
            if (-not $leaf) { continue }
            $relative = "$dirName/$leaf"
            if ($local.ContainsKey($relative)) { continue }
            if (@('.js', '.css') -notcontains [System.IO.Path]::GetExtension($name).ToLower()) { continue }

            Backup-RemoteFile $relative
            try {
                $req = [System.Net.FtpWebRequest]::Create("ftp://$hostName$remoteBase/$relative")
                $req.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
                $req.Credentials = $cred
                $req.UsePassive = $true
                $req.KeepAlive = $false
                $req.GetResponse().Close()
                Write-Host ("PRUNED: {0}" -f $relative)
                $removed++
            } catch {
                Write-Host ("PRUNE FAILED: {0} — {1}" -f $relative, $_.Exception.Message) -ForegroundColor Red
            }
        }
    }
    Write-Host ("Pruned {0} file(s)." -f $removed)
}
