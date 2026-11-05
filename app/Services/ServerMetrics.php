<?php

namespace App\Services;

class ServerMetrics
{
    public function snapshot(): array
    {
        // Detectar plataforma y leer las métricas crudas correspondientes
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $data = $isWindows ? $this->readWindows() : $this->readLinux();

        return [
            'hostname'   => gethostname(),
            'os'         => php_uname('s') . ' ' . php_uname('r'),
            'php'        => PHP_VERSION,
            'disk'       => $this->disk(),
            'memory'     => $this->memory($data, $isWindows),
            'cpu_pct'    => $data['cpu_pct'] ?? null,
            'uptime_s'   => $data['uptime_s'] ?? null,
            'cpu_temp_c' => $data['temp_c'] ?? null,
            'at'         => now()->toIso8601String(),
        ];
    }

    private function disk(): array
    {
        $path = base_path();
        $total = disk_total_space($path);
        $free  = disk_free_space($path);
        
        return [
            'total_gb' => round($total / 1e9, 1),
            'free_gb'  => round($free / 1e9, 1),
            'used_pct' => round(100 * ($total - $free) / $total, 1),
        ];
    }

    private function memory(?array $data, bool $isWindows): ?array
    {
        if (empty($data)) {
            return null;
        }

        // Windows trabaja en KB con comandos CIM; Linux en MB con 'free -m'
        if ($isWindows) {
            $totalMb = intdiv($data['mem_total_kb'] ?? 0, 1024);
            $freeMb  = intdiv($data['mem_free_kb'] ?? 0, 1024);
        } else {
            $totalMb = $data['mem_total_mb'] ?? 0;
            $freeMb  = $data['mem_free_mb'] ?? 0;
        }

        if ($totalMb === 0) {
            return null;
        }

        return [
            'total_mb'     => $totalMb,
            'available_mb' => $freeMb,
            'used_pct'     => round(100 * ($totalMb - $freeMb) / $totalMb, 1),
        ];
    }

    // Ejecuta comandos nativos de Linux de forma limpia y rápida
    private function readLinux(): ?array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }

        // 1. Memoria RAM usando 'free -m' (Columna total y disponible)
        $freeOutput = shell_exec("free -m | awk 'NR==2{print $2 \",\" $7}'");
        $ram = $freeOutput ? explode(',', trim($freeOutput)) : [];

        // 2. Uso de CPU (Porcentaje inverso del idle time de 'top')
        $cpuPct = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'");

        // 3. Tiempo encendido en segundos (Uptime) sacado de /proc/uptime
        $uptimeRaw = shell_exec("cat /proc/uptime | awk '{print $1}'");

        // 4. Temperatura de CPU (Lee la zona térmica principal en Linux Mint)
        $tempRaw = shell_exec("[ -f /sys/class/thermal/thermal_zone0/temp ] && cat /sys/class/thermal/thermal_zone0/temp");
        $tempC = $tempRaw ? round(intval(trim($tempRaw)) / 1000, 1) : null;

        return [
            'mem_total_mb' => isset($ram[0]) ? intval($ram[0]) : 0,
            'mem_free_mb'  => isset($ram[1]) ? intval($ram[1]) : 0,
            'cpu_pct'      => $cpuPct ? round(floatval(trim($cpuPct)), 1) : null,
            'uptime_s'     => $uptimeRaw ? intval(trim($uptimeRaw)) : null,
            'temp_c'       => $tempC,
        ];
    }

    // Ejecuta scripts de PowerShell en tiempo real y devuelve datos reales de Windows
    private function readWindows(): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        try {
            // 1. Obtener Memoria (Total y Libre en Kilobytes)
            $memRaw = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize, FreePhysicalMemory | ConvertTo-Json"');
            $mem = json_decode($memRaw, true);

            // 2. Obtener Uso de CPU (%)
            $cpuRaw = shell_exec('powershell -NoProfile -Command "Get-CimInstance Win32_Processor | Select-Object -ExpandProperty LoadPercentage"');
            $cpuPct = $cpuRaw !== null ? floatval(trim($cpuRaw)) : null;

            // 3. Obtener Uptime (Segundos desde el último encendido)
            $uptimeScript = '(Get-Date) - (Get-CimInstance Win32_OperatingSystem).LastBootUpTime | Select-Object -ExpandProperty TotalSeconds';
            $uptimeRaw = shell_exec("powershell -NoProfile -Command \"$uptimeScript\"");
            $uptimeS = $uptimeRaw !== null ? intval(trim($uptimeRaw)) : null;

            // 4. Intentar obtener Temperatura de CPU (Requiere soporte ACPI de la placa base)
            $tempRaw = shell_exec('powershell -NoProfile -Command "Get-CimInstance -Namespace root\wmi -ClassName MSAcpi_ThermalZoneTemperature | Select-Object -ExpandProperty CurrentTemperature"');
            $tempC = $tempRaw ? round((intval(trim($tempRaw)) - 2732) / 10, 1) : null;

            return [
                'mem_total_kb' => $mem['TotalVisibleMemorySize'] ?? 0,
                'mem_free_kb'  => $mem['FreePhysicalMemory'] ?? 0,
                'cpu_pct'      => $cpuPct,
                'uptime_s'     => $uptimeS,
                'temp_c'       => $tempC,
            ];
        } catch (\Exception $e) {
            return [
                'mem_total_kb' => 0,
                'mem_free_kb'  => 0,
                'cpu_pct'      => null,
                'uptime_s'     => null,
                'temp_c'       => null,
            ];
        }
    }
}
