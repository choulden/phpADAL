<?php
require(__DIR__ . '/includes/UrlBuilder.php');

$map = array(
 'phpADAL\Constants'			=>		__DIR__ . '/constants.php',
 'phpADAL\Adal'				=>		__DIR__ . '/adal.php',
 'phpADAL\Authentication\AuthenticationContext'=>	__DIR__ . '/Authentication/AuthenticationContext.php',
 'phpADAL\Authentication\Authority'	=>		__DIR__ . '/Authentication/Authority.php',
 'phpADAL\Exception\ExceptionEnum'	=>		__DIR__ . '/Exception/ExceptionEnum.php',
 'phpADAL\Exception\ExceptionCode'	=>		__DIR__ . '/Exception/ExceptionCode.php',
 'phpADAL\Exception\ADALException'	=>		__DIR__ . '/Exception/Exception.php',
 'phpADAL\HTTP\Request'			=>		__DIR__ . '/HTTP/Request.php',
 'phpADAL\HTTP\Response'		=>		__DIR__ . '/HTTP/Response.php',
 'phpADAL\Log\Logger'			=>		__DIR__ . '/Log/Logger.php',
 'phpADAL\Validation\Validation'	=>		__DIR__ . '/Validation/AbstractValidation.php',
 'phpADAL\Validation\StringValidation'	=>		__DIR__ . '/Validation/StringValidation.php',
 'phpADAL\Validation\UrlValidation'	=>		__DIR__ . '/Validation/UrlValidation.php',
 'phpADAL\Validation\UserCodeValidation'=>		__DIR__ . '/Validation/UserCodeValidation.php',
 'phpADAL\Validation\ConstantValidation'=>		__DIR__ . '/Validation/ConstantValidation.php',
 'phpADAL\Request\Code'			=>		__DIR__ . '/Request/Code.php',
 'phpADAL\Request\Token'		=>		__DIR__ . '/Request/Token.php',
 'phpADAL\Request\UserRealm'		=>		__DIR__ . '/Request/UserRealm.php',
 'phpADAL\Request\OAuth2'		=>		__DIR__ . '/Request/OAuth2.php',
 'phpADAL\Cache\CacheEntry'		=>		__DIR__ . '/Cache/CacheEntry.php',
 'phpADAL\Cache\CacheManager'		=>		__DIR__ . '/Cache/CacheManager.php',
 'phpADAL\Cache\DriverManager'		=>		__DIR__ . '/Cache/DriverManager.php',
 'phpADAL\Cache\Drivers\AbstractCacheDriver' =>		__DIR__ . '/Cache/Drivers/AbstractCacheDriver.php',
 'phpADAL\Cache\Drivers\MemoryCacheDriver' =>		__DIR__ . '/Cache/Drivers/Memory.php',
 'phpADAL\Cache\Drivers\FileCacheDriver' =>		__DIR__ . '/Cache/Drivers/File.php',
 'UUIDgen\UUID'				=>		__DIR__ . '/libs/UUIDgen/uuid.php',
);

spl_autoload_register(function ($class) use ($map) {
	if (isset($map[$class]))
	{
		require $map[$class];
	}
}, true);
?>
