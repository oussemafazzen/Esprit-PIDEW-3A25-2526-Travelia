<?php
require 'vendor/autoload.php';

use Symfony\Component\Security\Http\Authenticator\AbstractFormLoginAuthenticator;

if (class_exists(AbstractFormLoginAuthenticator::class)) {
    echo "Class AbstractFormLoginAuthenticator exists!\n";
} else {
    echo "Class AbstractFormLoginAuthenticator does NOT exist!\n";
}
