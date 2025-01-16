<?php
declare(strict_types=1);

namespace Ennacx\CakeMiddlewares\Enum;

enum MaintenanceCheckMethod {

    case FILE;

    case FLAG;

    case DATE;
}
