# Barrido de caracteres fuera del alfabeto latino.
# Se ejecuta sobre TODOS los archivos de texto de la aplicacion.
# Uso:  powershell -File barrido-caracteres.ps1 -Ruta <carpeta>

param(
    [Parameter(Mandatory = $true)]
    [string]$Ruta
)

$ErrorActionPreference = 'Stop'

# Rangos de escritura no latina que NO tienen nada que ver con el espanol de
# un estudio de abogados. Si aparece uno, es contaminacion.
$rangoSospechoso = @(
    @{ From = 0x0400; To = 0x04FF; Nombre = 'cirilico' },
    @{ From = 0x0590; To = 0x05FF; Nombre = 'hebreo' },
    @{ From = 0x0600; To = 0x06FF; Nombre = 'arabe' },
    @{ From = 0x0750; To = 0x077F; Nombre = 'arabe' },
    @{ From = 0x0900; To = 0x097F; Nombre = 'devanagari' },
    @{ From = 0x0E00; To = 0x0E7F; Nombre = 'tai' },
    @{ From = 0x3040; To = 0x30FF; Nombre = 'japones' },
    @{ From = 0x3130; To = 0x318F; Nombre = 'coreano hangul' },
    @{ From = 0x3400; To = 0x4DBF; Nombre = 'chino' },
    @{ From = 0x4E00; To = 0x9FFF; Nombre = 'chino' },
    @{ From = 0xA000; To = 0xA4CF; Nombre = 'yi' },
    @{ From = 0xAC00; To = 0xD7AF; Nombre = 'coreano hangul' },
    @{ From = 0xF900; To = 0xFAFF; Nombre = 'chino' },
    @{ From = 0xFF00; To = 0xFFEF; Nombre = 'cjk de ancho completo' }
)

# Se consideran correctos estos caracteres no latinos, que si aparecen es a
# proposito: comillas tipograficas, raya, copyright y signos matematicos.
$permitidos = @{
    0x2018 = 'comilla simple izquierda'
    0x2019 = 'comilla simple derecha'
    0x201C = 'comillas latinas izquierda'
    0x201D = 'comillas latinas derecha'
    0x2013 = 'raya corta'
    0x2014 = 'raya larga'
    0x2026 = 'puntos suspensivos'
    0x00A9 = 'copyright'
    0x00AE = 'marca registrada'
    0x00B0 = 'grado'
    0x00D7 = 'multiplicacion'
    0x00F7 = 'division'
}

$extensiones = @(
    '.php', '.md', '.ts', '.vue', '.json', '.yml', '.yaml', '.sh', '.xml',
    '.conf', '.ini', '.env', '.example', '.txt', '.sql', '.gitignore'
)
$nombresSinExtension = @('Dockerfile', 'Caddyfile', '.env.example', '.gitignore')

$total = 0
$archivosAfectados = @()

Get-ChildItem $Ruta -Recurse -File -Force | Where-Object {
    $extensiones -contains $_.Extension -or $nombresSinExtension -contains $_.Name
} | ForEach-Object {
    $archivo = $_
    $numeroLinea = 0
    $lineas = [System.IO.File]::ReadAllLines($archivo.FullName)
    for ($i = 0; $i -lt $lineas.Count; $i++) {
        $numeroLinea = $i + 1
        $linea = $lineas[$i]
        foreach ($caracter in $linea.ToCharArray()) {
            $codigo = [int]$caracter

            if ($permitidos.ContainsKey($codigo)) {
                continue
            }

            $sospechoso = $null
            foreach ($rango in $rangoSospechoso) {
                if ($codigo -ge $rango.From -and $codigo -le $rango.To) {
                    $sospechoso = $rango.Nombre
                    break
                }
            }

            if ($sospechoso -ne $null) {
                $total++
                $codigos = ($linea.ToCharArray() | ForEach-Object { [int]$_ }) -join ','
                Write-Output ("ARCHIVO : {0}" -f $archivo.FullName)
                Write-Output ("LINEA  : {0}" -f $numeroLinea)
                Write-Output ("TEXTO  : {0}" -f $linea.Trim())
                Write-Output ("MARCA  : {0} U+{1:X4}" -f $sospechoso, $codigo)
                Write-Output ("CODIGOS : {0}" -f $codigos)
                Write-Output ''
                $archivosAfectados += $archivo.FullName
                break
            }
        }
    }
}

Write-Output ("=== TOTAL MARCADAS: {0} en {1} archivo(s) ===" -f $total, ($archivosAfectados | Select-Object -Unique).Count)