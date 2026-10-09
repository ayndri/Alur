<?php

namespace App\Exceptions;

use RuntimeException;

/** Aturan papan yang dilanggar (kolom penuh, kartu sudah berubah, dsb.). Pesannya untuk pengguna. */
class FlowException extends RuntimeException
{
}
