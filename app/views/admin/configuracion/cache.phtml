<div class="container-fluid">
    <div class="page-header">
        <h1><?= $title ?></h1>
    </div>
    
    <?php View::partial('flash') ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Configuración del Sistema de Cache</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="cache_default">Driver por Defecto</label>
                            <select class="form-control" id="cache_default" name="cache_default">
                                <option value="file" <?= ($config_cache['default'] ?? 'file') == 'file' ? 'selected' : '' ?>>Archivos</option>
                                <option value="memcached" <?= ($config_cache['default'] ?? '') == 'memcached' ? 'selected' : '' ?>>Memcached</option>
                                <option value="redis" <?= ($config_cache['default'] ?? '') == 'redis' ? 'selected' : '' ?>>Redis</option>
                                <option value="array" <?= ($config_cache['default'] ?? '') == 'array' ? 'selected' : '' ?>>Array (solo para pruebas)</option>
                            </select>
                            <small class="text-muted">Seleccione el sistema de cache a utilizar</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="cache_prefix">Prefijo para Claves</label>
                            <input type="text" class="form-control" id="cache_prefix" name="cache_prefix" 
                                   value="<?= htmlspecialchars($config_cache['prefix'] ?? 'app_') ?>">
                            <small class="text-muted">Útil cuando se comparte servidor de cache con múltiples aplicaciones</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="cache_ttl">TTL por Defecto (segundos)</label>
                            <input type="number" class="form-control" id="cache_ttl" name="cache_ttl" 
                                   value="<?= $config_cache['ttl'] ?? 3600 ?>" min="60" max="86400">
                            <small class="text-muted">Tiempo de vida de los elementos en cache (60-86400 segundos)</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="cache_enabled" value="1" 
                                           <?= ($config_cache['enabled'] ?? true) ? 'checked' : '' ?>>
                                    Habilitar sistema de cache
                                </label>
                            </div>
                            <small class="text-muted">Deshabilitar solo para desarrollo o troubleshooting</small>
                        </div>
                        
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">Configuración por Driver</h4>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>File (Archivos)</h5>
                                        <div class="form-group">
                                            <label for="file_path">Ruta de Almacenamiento</label>
                                            <input type="text" class="form-control" id="file_path" name="file_path" 
                                                   value="<?= htmlspecialchars($config_cache['stores']['file']['path'] ?? APP_PATH . 'temp/cache/') ?>" readonly>
                                            <small class="text-muted">Los archivos de cache se almacenan aquí</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>Memcached</h5>
                                        <div class="form-group">
                                            <label for="memcached_host">Servidor</label>
                                            <input type="text" class="form-control" id="memcached_host" name="memcached_host" 
                                                   value="<?= htmlspecialchars($config_cache['stores']['memcached']['servers'][0]['host'] ?? '127.0.0.1') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="memcached_port">Puerto</label>
                                            <input type="number" class="form-control" id="memcached_port" name="memcached_port" 
                                                   value="<?= $config_cache['stores']['memcached']['servers'][0]['port'] ?? 11211 ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Recomendaciones:</strong><br>
                            1. Use "File" para desarrollo y proyectos pequeños<br>
                            2. Use Memcached o Redis para producción y alto tráfico<br>
                            3. Verifique que los servicios estén corriendo antes de cambiar la configuración
                        </div>
                        
                        <button type="submit" name="guardar_cache" class="btn btn-primary">
                            <i class="glyphicon glyphicon-save"></i> Guardar Configuración
                        </button>
                        <a href="<?= PUBLIC_PATH ?>configuracion/limpiar_cache" class="btn btn-warning">
                            <i class="glyphicon glyphicon-trash"></i> Limpiar Cache
                        </a>
                        <a href="<?= PUBLIC_PATH ?>configuracion" class="btn btn-default">
                            <i class="glyphicon glyphicon-arrow-left"></i> Volver
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>