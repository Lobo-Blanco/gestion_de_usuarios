<div class="container-fluid">
    <div class="page-header">
        <h1><?= $title ?></h1>
        <p class="lead">Registro de cambios en la configuración del sistema</p>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Filtros de Búsqueda</h3>
                </div>
                <div class="panel-body">
                    <form method="get" action="" class="form-inline">
                        <div class="form-group">
                            <label for="usuario" class="sr-only">Usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" 
                                   placeholder="Usuario" value="<?= htmlspecialchars($filterUser) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo" class="sr-only">Tipo</label>
                            <select class="form-control" id="tipo" name="tipo">
                                <option value="">Todos los tipos</option>
                                <option value="auth" <?= $filterType == 'auth' ? 'selected' : '' ?>>Autenticación</option>
                                <option value="app" <?= $filterType == 'app' ? 'selected' : '' ?>>Aplicación</option>
                                <option value="database" <?= $filterType == 'database' ? 'selected' : '' ?>>Base de Datos</option>
                                <option value="acl" <?= $filterType == 'acl' ? 'selected' : '' ?>>ACL</option>
                                <option value="cache" <?= $filterType == 'cache' ? 'selected' : '' ?>>Cache</option>
                                <option value="backup" <?= $filterType == 'backup' ? 'selected' : '' ?>>Backup</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_desde" class="sr-only">Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                   value="<?= htmlspecialchars($filterDateFrom) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_hasta" class="sr-only">Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?= htmlspecialchars($filterDateTo) ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="glyphicon glyphicon-search"></i> Filtrar
                        </button>
                        <a href="<?= PUBLIC_PATH ?>configuracion/historial" class="btn btn-default">
                            <i class="glyphicon glyphicon-refresh"></i> Limpiar
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Registro de Cambios</h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($changes)): ?>
                        <div class="alert alert-info">
                            <i class="glyphicon glyphicon-info-sign"></i>
                            No se encontraron registros de cambios.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Usuario</th>
                                        <th>Configuración</th>
                                        <th>Descripción</th>
                                        <th>IP</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($changes as $change): ?>
                                    <tr>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($change['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($change['user_codigo'])): ?>
                                                <span class="label label-primary"><?= htmlspecialchars($change['user_codigo']) ?></span>
                                            <?php else: ?>
                                                <span class="label label-default">Sistema</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code><?= $change['config_name'] ?></code>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($change['description']) ?>
                                            <?php if (!empty($change['changes'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <a href="#" onclick="showChanges(<?= $change['id'] ?>)">
                                                        <i class="glyphicon glyphicon-eye-open"></i> Ver cambios
                                                    </a>
                                                </small>
                                                <div id="changes-<?= $change['id'] ?>" style="display: none; margin-top: 10px;">
                                                    <pre class="bg-light p-2"><?= json_encode(json_decode($change['changes'], true), JSON_PRETTY_PRINT) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= $change['ip_address'] ?? 'N/A' ?></small>
                                        </td>
                                        <td>
                                            <a href="<?= PUBLIC_PATH ?>configuracion/restaurar/<?= $change['id'] ?>" 
                                               class="btn btn-xs btn-warning" title="Restaurar esta versión">
                                                <i class="glyphicon glyphicon-time"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($currentPage > 1): ?>
                                <li>
                                    <a href="<?= PUBLIC_PATH ?>configuracion/historial?page=<?= $currentPage-1 ?><?= $this->buildQueryString() ?>" 
                                       aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="<?= $i == $currentPage ? 'active' : '' ?>">
                                    <a href="<?= PUBLIC_PATH ?>configuracion/historial?page=<?= $i ?><?= $this->buildQueryString() ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($currentPage < $totalPages): ?>
                                <li>
                                    <a href="<?= PUBLIC_PATH ?>configuracion/historial?page=<?= $currentPage+1 ?><?= $this->buildQueryString() ?>" 
                                       aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="glyphicon glyphicon-info-sign"></i>
                            Mostrando <?= count($changes) ?> registros. 
                            <?php if (isset($total) && $total > count($changes)): ?>
                                Total de registros: <?= $total ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showChanges(id) {
    var element = document.getElementById('changes-' + id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
    return false;
}
</script>