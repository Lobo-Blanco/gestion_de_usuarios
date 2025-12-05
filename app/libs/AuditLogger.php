<?php
// app/lib/AuditLogger.php

class AuditLogger
{
    private $db;
    
    public function __construct()
    {
        $this->db = Db::factory();
    }

    public function __destruct() {
        unset($this->db);
    }

    public function logConfigChange($userId, $configName, $description, $changes = [])
    {
        try {
            $sql = "INSERT INTO config_audit_log 
                    (user_id, config_name, description, changes, ip_address, created_at) 
                    VALUES ($userId, $configName, $description, " .  json_encode($changes) . ", " . $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' . ", NOW())";
            
            $stmt = $this->db->query($sql);
            
            return true;
        } catch (Exception $e) {
            Log::error("Error en auditoría: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRecentChanges($configName = null, $limit = 10)
    {
        try {
            $sql = "SELECT cl.*, u.codigo as user_codigo 
                    FROM config_audit_log cl
                    LEFT JOIN usuarios u ON cl.user_id = u.id";
            
            $params = [];
            
            if ($configName) {
                $sql .= " WHERE cl.config_name = ?";
                $params[] = $configName;
            }
            
            $sql .= " ORDER BY cl.created_at DESC LIMIT ?";
            $params[] = (int)$limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $row;
            }
            
            return $results;
        } catch (Exception $e) {
            Log::error("Error obteniendo cambios: " . $e->getMessage());
            return [];
        }
    }
    
    public function logBackupCreated($userId, $backupFile, $type)
    {
        return $this->logConfigChange(
            $userId,
            'backup',
            "Backup {$type} creado: " . basename($backupFile)
        );
    }
    
    public function logCacheCleared($userId, $cacheTypes, $results)
    {
        return $this->logConfigChange(
            $userId,
            'cache',
            'Cache limpiada: ' . implode(', ', $cacheTypes),
            ['types' => $cacheTypes, 'results' => $results]
        );
    }
}