<?php

namespace App\Application\Common\Idempotency;

use RuntimeException;

class IdempotencyConcurrentStart extends RuntimeException
{

}
