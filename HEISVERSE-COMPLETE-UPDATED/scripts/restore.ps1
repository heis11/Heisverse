# =====================================================================
# HEISVERSE — Database Restoration Script (Windows PowerShell)
# Restores a specified .sql dump into the MySQL 8 container.
# =====================================================================

param (
    [Parameter(Mandatory=$false)]
    [string]$File = "database.sql"
)

if (-not (Test-Path $File)) {
    Write-Host "❌ File '$File' not found." -ForegroundColor Red
    exit 1
}

Write-Host "⚠️ Restoring HEISVERSE database from '$File'..." -ForegroundColor Yellow

Get-Content $File | docker compose exec -T db mysql -u root -pheisverse_root_pass_2026 heisverse

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Database restored successfully!" -ForegroundColor Green
} else {
    Write-Host "❌ Database restore encountered an error." -ForegroundColor Red
}
