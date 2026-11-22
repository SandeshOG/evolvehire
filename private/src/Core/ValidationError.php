<?php
declare(strict_types=1);

namespace Evolve\Core;

/**
 * Validation error that the UI shows next to the form.
 * Kept in its own file so services can `use Evolve\Core\ValidationError`
 * while the global helper functions stay unnamespaced in helpers.php.
 */
class ValidationError extends \InvalidArgumentException
{
}
