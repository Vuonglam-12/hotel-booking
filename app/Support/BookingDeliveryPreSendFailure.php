<?php

namespace App\Support;

/** Only use when no message was handed to any transport. Unknown failures are uncertain. */
final class BookingDeliveryPreSendFailure extends \RuntimeException {}
