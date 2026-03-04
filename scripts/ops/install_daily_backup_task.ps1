param(
    [string]$ProjectRoot = "C:\xampp\htdocs\Romar",
    [string]$PhpBin = "C:\xampp\php\php.exe",
    [string]$TaskName = "RomarDailyDbBackup",
    [string]$StartTime = "01:30",
    [string]$RunAs = "SYSTEM"
)

$runner = Join-Path $ProjectRoot "scripts\ops\run_daily_backup.ps1"
$psExe = "C:\WINDOWS\System32\WindowsPowerShell\v1.0\powershell.exe"
$taskCommand = "$psExe -NoProfile -ExecutionPolicy Bypass -File `"$runner`" -ProjectRoot `"$ProjectRoot`" -PhpBin `"$PhpBin`""

if ($RunAs -eq "SYSTEM") {
    schtasks /Create /F /SC DAILY /ST $StartTime /TN $TaskName /TR $taskCommand /RU SYSTEM /RL HIGHEST
} else {
    schtasks /Create /F /SC DAILY /ST $StartTime /TN $TaskName /TR $taskCommand
}
