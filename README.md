# Symfony service memoization bundle

This bundle provides memoization for your services - every time you call the same method with the same arguments
a cached response will be returned instead of executing the method.

## Installation

Requires php 8.1+.

`composer require rikudou/memoize-bundle`

Afterwards add this to your `composer.json` autoload PSR-4 section: `"App\\Memoized\\": "memoized/"`

Example on fresh Symfony project:

```json5
{
  "autoload": {
    "psr-4": {
      "App\\": "src/",
      "App\\Memoized\\": "memoized/" // add this line
    }
  }
}
```

## Usage

You can simply use the provided attributes.

- `#[Memoizable]` - Every class that has memoized methods must have this attribute.
- `#[Memoize]` - An attribute that can be used on classes or methods to mark given class/method as memoized. Has an
optional parameter with number of seconds for cache validity. Default to `-1` which means until end of the process
(meaning end of request in standard php-fpm and apache2 configurations)
- `#[NoMemoize]` - An attribute that allows you to mark a method as non memoized in case you made the whole class
memoized.

Every memoized class needs to implement at least one interface that you use to typehint the given service.
A proxy class is created for each service with `#[Memoizable]` attribute. This proxy class decorates your service
so that every time you ask for your service the proxy gets injected instead.

For non-memoized methods the proxy class simply passes the arguments through to your service while for memoized
methods it also creates a cache key based on the parameters and looks for the result into the cache.

> The proxy classes are generated in a compiler pass, meaning the proxy creation doesn't add overhead once container
> has been dumped.

### Examples:

**Class with one memoized method**

```php
<?php

use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Rikudou\MemoizeBundle\Attribute\Memoize;

interface CalculatorInterface
{
    public function add(int $a, int $b): int;
    public function sub(int $a, int $b): int;
}

#[Memoizable]
final class Calculator implements CalculatorInterface
{
    #[Memoize]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function sub(int $a, int $b): int
    {
        return $a - $b;
    }
}
```

This class will simply have the `add()` method memoized while `sub()` will not be memoized.

**Whole class memoized**

```php
<?php

use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Rikudou\MemoizeBundle\Attribute\Memoize;

#[Memoizable]
#[Memoize]
final class Calculator implements CalculatorInterface
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function sub(int $a, int $b): int
    {
        return $a - $b;
    }
}
```

Here both `add()` and `sub()` methods will be memoized.

**Whole class memoized without one method**

```php
<?php

use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Rikudou\MemoizeBundle\Attribute\Memoize;
use Rikudou\MemoizeBundle\Attribute\NoMemoize;

#[Memoizable]
#[Memoize]
final class Calculator implements CalculatorInterface
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    #[NoMemoize]
    public function sub(int $a, int $b): int
    {
        return $a - $b;
    }
}
```

Here the whole class will be memoized except for `sub()` method due to the `#[NoMemoize]` attribute.

**Custom memoization validity**

```php
<?php

use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Rikudou\MemoizeBundle\Attribute\Memoize;

#[Memoizable]
#[Memoize(seconds: 30)]
final class Calculator implements CalculatorInterface
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function sub(int $a, int $b): int
    {
        return $a - $b;
    }
}
```

Here the whole class will be memoized with the cache validity set to 30 seconds.

## Configuration

> Tip: Create the default configuration file by using this command: 
> `php bin/console config:dump rikudou_memoize > config/packages/rikudou_memoize.yaml`

There are these configurations:

- `enabled`
- `default_memoize_seconds`
- `cache_service`
- `key_specifier_service`

### enabled

Simple boolean to enable or disable memoization.

### default_memoize_seconds

This parameter sets the default lifetime of memoized cache in seconds.
Defaults to `-1` which means until end of process (end of request in standard php-fpm or apache2 configurations).

Note that when the default value of `-1` is used the `cache_service` configuration is ignored.

### cache_service

This parameter controls which cache service is used for memoization. Defaults to `cache.app` meaning the default
cache for your application.

If the `default_memoize_seconds` is set to `-1` this config is ignored and a default in-memory implementation
is used (service `rikudou.memoize.internal_cache`, class [`InMemoryCachePool`](src/Cache/InMemoryCachePool.php)).

### key_specifier_service

This parameter allows you to specify a service that will alter the cache key. This is useful if your app relies on
some kind of global state (like currently authenticated user etc.). It must implement the
[CacheKeySpecifier](src/Cache/KeySpecifier/CacheKeySpecifier.php) interface.

### Default configuration

Generated by `php bin/console config:dump rikudou_memoize`

```yaml
# Default configuration for extension with alias: "rikudou_memoize"
rikudou_memoize:

  # Whether memoization is enabled or not.
  enabled:              true

  # The default memoization period if none is specified in attribute. -1 means until end of request.
  default_memoize_seconds: -1

  # The default cache service to use. If default_memoize_seconds is set to -1 this setting is ignored and internal service is used.
  cache_service:        cache.app

  # The service to use to alter the cache key. Useful if you need to alter the cache key based on some global state.
  key_specifier_service: rikudou.memoize.key_specifier.null
```

## Example proxy class

The first code block is the original class, the second code block is the proxy.

```php
<?php

namespace App\Service;

use Rikudou\MemoizeBundle\Attribute\Memoizable;
use Rikudou\MemoizeBundle\Attribute\Memoize;
use Rikudou\MemoizeBundle\Attribute\NoMemoize;
use RuntimeException;

#[Memoizable]
#[Memoize(seconds: 10)]
class Calculator implements CalculatorInterface
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    #[Memoize(seconds: -1)]
    public function sub(int $a, int $b): int
    {
        return $a - $b;
    }

    #[NoMemoize]
    public function mul(int $a, int $b): int
    {
        return $a * $b;
    }

    public function someVoidMethod(): void
    {
    }

    public function throwException(): never
    {
        throw new RuntimeException();
    }
}
```

```php
<?php

namespace App\Memoized;

final class Calculator_Proxy_422705a42881a5490e7996fe93245734 implements \App\Service\CalculatorInterface
{
	public function __construct(
		private readonly \App\Service\Calculator $original,
		private readonly \Psr\Cache\CacheItemPoolInterface $cache,
		private readonly \Rikudou\MemoizeBundle\Cache\InMemoryCachePool $internalCache,
		private readonly \Rikudou\MemoizeBundle\Cache\KeySpecifier\CacheKeySpecifier $cacheKeySpecifier,
	) {}

	public function add(int $a, int $b): int {
		$cacheKey = '';
		$cacheKey .= serialize($a);
		$cacheKey .= serialize($b);
		$cacheKey .= $this->cacheKeySpecifier->generate();
		$cacheKey = hash('sha512', $cacheKey);
		$cacheKey = "rikudou_memoize_AppServiceCalculator_add_{$cacheKey}";

		$cacheItem = $this->cache->getItem($cacheKey);
		if ($cacheItem->isHit()) {
			return $cacheItem->get();
		}
		$cacheItem->set($this->original->add($a, $b));
		$cacheItem->expiresAfter(10);
		$this->cache->save($cacheItem);

		return $cacheItem->get();
	}

	public function sub(int $a, int $b): int {
		$cacheKey = '';
		$cacheKey .= serialize($a);
		$cacheKey .= serialize($b);
		$cacheKey .= $this->cacheKeySpecifier->generate();
		$cacheKey = hash('sha512', $cacheKey);
		$cacheKey = "rikudou_memoize_AppServiceCalculator_sub_{$cacheKey}";

		$cacheItem = $this->internalCache->getItem($cacheKey);
		if ($cacheItem->isHit()) {
			return $cacheItem->get();
		}
		$cacheItem->set($this->original->sub($a, $b));
		$cacheItem->expiresAfter(0);
		$this->internalCache->save($cacheItem);

		return $cacheItem->get();
	}

	public function mul(int $a, int $b): int {
		return $this->original->mul($a, $b);
	}

	public function someVoidMethod(): void {
		$cacheKey = '';
		$cacheKey .= $this->cacheKeySpecifier->generate();
		$cacheKey = hash('sha512', $cacheKey);
		$cacheKey = "rikudou_memoize_AppServiceCalculator_someVoidMethod_{$cacheKey}";

		$cacheItem = $this->cache->getItem($cacheKey);
		if ($cacheItem->isHit()) {
			return;
		}
		$cacheItem->set($this->original->someVoidMethod());
		$cacheItem->expiresAfter(10);
		$this->cache->save($cacheItem);

	}

	public function throwException(): never {
		$this->original->throwException();
	}

}
```
