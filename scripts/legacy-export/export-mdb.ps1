# Exports the legacy Access tables the import needs to CSV, reading COPIES so the originals stay untouched.
# Needs the 32-bit Access driver, so run it with 32-bit PowerShell, then convert the CSVs to the workbooks:
#
#   & "$env:WINDIR\SysWOW64\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File scripts\legacy-export\export-mdb.ps1
#   php scripts\legacy-export\csv-to-xlsx.php
#   php artisan app:import-legacy-data --dry-run
#
# Output goes to storage/app/legacy-export (git-ignored, not web-served). It holds real customer data.
# The script stops if a table of the old program's formulas that the import does not read has rows.
param(
    [string]$Source = (Join-Path $PSScriptRoot '..\..\public\almadina'),
    [string]$Target = (Join-Path $PSScriptRoot '..\..\storage\app\legacy-export')
)
$ErrorActionPreference = 'Stop'

if ([IntPtr]::Size -ne 4) { throw 'Run this with 32-bit PowerShell: the Access driver on this machine is 32-bit.' }

$copies = Join-Path $Target 'mdb-copies'
$csv = Join-Path $Target 'csv'
New-Item -ItemType Directory -Force -Path $copies, $csv | Out-Null

$tables = @{
    adres = @('tel')
    all = @('main', 'main2', 'stor', 'kind')
    invo_w = @('sadr', 'seq', 'diss', 'return', 'sarf')
    invo_s = @('sadr', 'seq', 'return', 'sarf')
    eaco = @('emp', 'hodor', 'cost')
}

# Tables of the stock and party balance formulas in qry.mdb that hold no rows today, so the import
# does not read them.
$mustBeEmpty = @{
    all = @('gard')
    invo_w = @('extra', 'trans', 'amana', 'costsarf', 'costsadr', 'sadrg', 'returng', 'slf', 'dbcr')
    invo_s = @('extra', 'bad', 'amana', 'costsarf', 'costsadr', 'sadrg', 'returng', 'slf', 'diss')
}

function Open-Copy([string]$db) {
    $original = Join-Path $Source "$db.mdb"
    $copy = Join-Path $copies "$db.mdb"
    Copy-Item $original $copy -Force
    $connection = New-Object System.Data.Odbc.OdbcConnection("Driver={Microsoft Access Driver (*.mdb)};Dbq=$copy;ReadOnly=1;Exclusive=0;")
    $connection.Open()
    return $connection
}

function Format-Cell($value) {
    if ($value -is [DBNull]) { return '' }
    if ($value -is [DateTime]) { return $value.ToString('yyyy-MM-dd HH:mm:ss', [Globalization.CultureInfo]::InvariantCulture) }
    if ($value -is [byte[]]) { return '' }
    if ($value -is [IFormattable]) { return $value.ToString($null, [Globalization.CultureInfo]::InvariantCulture) }
    return [string]$value
}

function Quote-Cell([string]$text) { return '"' + $text.Replace('"', '""') + '"' }

foreach ($db in $mustBeEmpty.Keys) {
    $connection = Open-Copy $db
    foreach ($table in $mustBeEmpty[$db]) {
        $command = $connection.CreateCommand()
        $command.CommandText = "SELECT COUNT(*) FROM [$table]"
        $rows = [int]$command.ExecuteScalar()
        if ($rows -gt 0) { throw "$db.$table has $rows rows: it is part of the old stock formula and the import does not read it yet." }
    }
    $connection.Close()
}

$utf8 = New-Object System.Text.UTF8Encoding($false)
foreach ($db in $tables.Keys) {
    $connection = Open-Copy $db
    foreach ($table in $tables[$db]) {
        $command = $connection.CreateCommand()
        $command.CommandText = "SELECT * FROM [$table]"
        $command.CommandTimeout = 300
        $reader = $command.ExecuteReader()
        $writer = New-Object System.IO.StreamWriter((Join-Path $csv "${db}__$table.csv"), $false, $utf8)
        try {
            $names = 0..($reader.FieldCount - 1) | ForEach-Object { Quote-Cell $reader.GetName($_) }
            $writer.WriteLine($names -join ',')
            $count = 0
            while ($reader.Read()) {
                $cells = New-Object string[] $reader.FieldCount
                for ($i = 0; $i -lt $reader.FieldCount; $i++) { $cells[$i] = Quote-Cell (Format-Cell $reader.GetValue($i)) }
                $writer.WriteLine($cells -join ',')
                $count++
            }
            Write-Output "$db.$table rows=$count"
        } finally {
            $writer.Close()
            $reader.Close()
        }
    }
    $connection.Close()
}

Remove-Item $copies -Recurse -Force
