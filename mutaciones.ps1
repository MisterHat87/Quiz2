param(
    [ValidateSet('inicial', 'final')]
    [string]$Etapa = 'inicial',
    [ValidatePattern('^[a-f0-9]{16}$')]
    [string]$Id
)

$ErrorActionPreference = 'Stop'
$mutationRuntime = Join-Path $PSScriptRoot 'storage/app/private/mutation-runtime'
$mutationOutput = Join-Path $PSScriptRoot 'storage/app/private'
$previousScanDirectory = $env:PHP_INI_SCAN_DIR
$previousXdebugMode = $env:XDEBUG_MODE
$previousPaoDisable = $env:PAO_DISABLE
$mutationExitCode = 1

Push-Location $PSScriptRoot

try {
    New-Item -ItemType Directory -Force -Path $mutationRuntime | Out-Null
    $phpExecutable = (Get-Command php -ErrorAction Stop).Source
    $env:PAO_DISABLE = '1'
    $env:XDEBUG_MODE = 'coverage'
    $hasCoverageDriver = '<?php echo extension_loaded("xdebug") || extension_loaded("pcov") ? "yes" : "no";' | & $phpExecutable

    if ($hasCoverageDriver -ne 'yes') {
        $phpBuild = '<?php echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION, ":", PHP_ZTS, ":", PHP_INT_SIZE;' | & $phpExecutable

        if ($phpBuild -ne '8.3:1:8') {
            throw 'Este instalador local requiere PHP 8.3 TS x64. Para otra version, instala el Xdebug correspondiente desde https://xdebug.org/download.'
        }

        $extensionPath = Join-Path $mutationRuntime 'php_xdebug.dll'

        if (-not (Test-Path -LiteralPath $extensionPath)) {
            Invoke-WebRequest -Uri 'https://xdebug.org/files/php_xdebug-3.5.3-8.3-ts-vs16-x86_64.dll' -OutFile $extensionPath
        }

        $extensionIni = 'zend_extension="' + $extensionPath.Replace('\', '/') + '"' + [Environment]::NewLine + 'xdebug.mode=coverage'
        Set-Content -LiteralPath (Join-Path $mutationRuntime '99-xdebug.ini') -Encoding ASCII -Value $extensionIni
        $env:PHP_INI_SCAN_DIR = $previousScanDirectory + ';' + $mutationRuntime
    }

    $bootstrapPhp = @'
<?php

require_once dirname(__DIR__, 4).'/vendor/autoload.php';

// Pest 4 intenta ejecutar el script sin php.exe y cmd interpreta los | del filtro.
PHPUnit\Event\Facade::instance()->registerSubscriber(new class implements PHPUnit\Event\TestRunner\ExecutionStartedSubscriber
{
    public function notify(PHPUnit\Event\TestRunner\ExecutionStarted $event): void
    {
        $runner = Pest\Support\Container::getInstance()->get(Pest\Mutate\Contracts\MutationTestRunner::class);

        if ($runner->isEnabled()) {
            $runner->setOriginalArguments([PHP_BINARY, dirname(__DIR__, 4).'/vendor/bin/pest', '--colors=never']);
        }
    }
});

require dirname(__DIR__, 4).'/vendor/bin/pest';
'@
    Set-Content -LiteralPath (Join-Path $mutationRuntime 'pest-bootstrap.php') -Encoding ASCII -Value $bootstrapPhp

    $mutationArguments = @('--mutate', '--path=app/Models/Grupo.php', '--no-cache', '--colors=never')

    if ($Etapa -eq 'final') {
        $mutationArguments += '--min=80'
    }

    if ($Id) {
        $mutationArguments += '--id=' + $Id
        $logPath = Join-Path $mutationOutput ('mutation-' + $Id + '.txt')
    } else {
        $logPath = Join-Path $mutationOutput ('mutation-' + $Etapa + '.txt')

        if (Test-Path -LiteralPath $logPath) {
            throw ('Ya existe la evidencia ' + $logPath + '. Conserva ese archivo antes de repetir la corrida.')
        }
    }

    & $phpExecutable -v | Tee-Object -FilePath $logPath
    & $phpExecutable (Join-Path $mutationRuntime 'pest-bootstrap.php') @mutationArguments 2>&1 | Tee-Object -FilePath $logPath -Append
    $mutationExitCode = $LASTEXITCODE
    Write-Host ('Evidencia guardada en ' + $logPath)
} finally {
    $env:PHP_INI_SCAN_DIR = $previousScanDirectory
    $env:XDEBUG_MODE = $previousXdebugMode
    $env:PAO_DISABLE = $previousPaoDisable
    Pop-Location
}

exit $mutationExitCode
