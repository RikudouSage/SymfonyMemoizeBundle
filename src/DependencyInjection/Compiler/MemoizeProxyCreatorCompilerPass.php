<?php

namespace Rikudou\MemoizeBundle\DependencyInjection\Compiler;

use JetBrains\PhpStorm\Pure;
use LogicException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Rikudou\MemoizeBundle\Attribute\Memoize;
use Rikudou\MemoizeBundle\Attribute\NoMemoize;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use UnitEnum;

class MemoizeProxyCreatorCompilerPass implements CompilerPassInterface
{
    private ContainerBuilder $container;

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $services = array_keys($container->findTaggedServiceIds('rikudou.memoize.memoizable_service'));

        foreach ($services as $service) {
            $definition = $container->getDefinition($service);
            if (!is_string($definition->getClass()) || !class_exists($definition->getClass())) {
                continue;
            }

            $reflection = new ReflectionClass($definition->getClass());
            $interfaces = $reflection->getInterfaces();
            if (!count($interfaces)) {
                throw new LogicException("Cannot memoize class '{$definition->getClass()}' without interfaces");
            }
            $proxyClass = $this->createProxyClass($definition, $service, $reflection, $interfaces);

            $newDefinition = new Definition($proxyClass, [
                new Reference('.inner'),
                new Reference($container->getParameter('rikudou.memoize.cache_service')),
            ]);
            $newDefinition->setDecoratedService($service);
            $container->setDefinition($proxyClass, $newDefinition);
        }
    }

    /**
     * @param array<ReflectionClass> $interfaces
     */
    private function createProxyClass(
        Definition      $serviceDefinition,
        string          $serviceId,
        ReflectionClass $classReflection,
        array           $interfaces
    ): string {
        $suffix = null;
        if ($filename = $classReflection->getFileName()) {
            $suffix = md5_file($filename);
        }
        if (!is_string($suffix)) {
            $suffix = bin2hex(random_bytes(16));
        }

        $namespace = "App\\Memoized";
        $className = "{$classReflection->getShortName()}_Proxy_{$suffix}";

        $classContent = "<?php\n\n";
        $classContent .= "namespace {$namespace};\n\n";

        $classContent .= "final class {$className} implements ";
        $classContent .= $this->getInterfacesString(...$interfaces);
        $classContent .= "\n{\n";

        $classContent .= $this->getConstructor($serviceDefinition);
        $classContent .= "\n\n";

        foreach ($classReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__construct') {
                continue;
            }
            $classContent .= $this->getMethod($method, $serviceId);
            $classContent .= "\n\n";
        }

        $classContent .= "}\n";

        $path = $this->saveClass($classContent, $className);
        require $path;

        return "{$namespace}\\{$className}";
    }

    private function getInterfacesString(ReflectionClass ...$interfaces): string
    {
        return implode(', ', array_map(
            fn(ReflectionClass $interface) => "\\{$interface->getName()}",
            $interfaces,
        ));
    }

    private function getConstructor(Definition $serviceDefinition): string
    {
        $originalClass = $serviceDefinition->getClass();
        $cacheService = $this->container->getParameter('rikudou.memoize.cache_service');
        $cacheServiceDefinition = $this->container->getDefinition($cacheService);

        $constructor = "\tpublic function __construct(\n";
        $constructor .= "\t\tprivate readonly \\{$originalClass} \$original,\n";
        $constructor .= "\t\tprivate readonly \\{$cacheServiceDefinition->getClass()} \$cache,\n";
        $constructor .= "\t) {}";

        return $constructor;
    }

    private function getMethod(ReflectionMethod $method, string $serviceId): string
    {
        if (!$this->shouldMemoize($method)) {
            return $this->getNonMemoizedMethod($method);
        }

        return $this->getMemoizedMethod($method, $serviceId);
    }

    private function getMemoizedMethod(ReflectionMethod $method, string $serviceId): string
    {
        $parameters = implode(', ', $this->getParameterDefinitions($method->getParameters()));
        $parametersCall = $this->getParametersAtCallTime($method->getParameters());
        $parametersCallString = implode(', ', $parametersCall);
        $serviceName = preg_replace('@[^a-zA-Z0-9_.]@', '', $serviceId);
        $attribute = $this->getAttribute($method, Memoize::class)
            ?? $this->getAttribute($method->getDeclaringClass(), Memoize::class);
        assert($attribute !== null);
        $expiresAfter = $attribute->seconds
            ?? $this->container->getParameter('rikudou.memoize.default_memoize_seconds');

        $returnTypeString = '';
        if ($returnType = $method->getReturnType()) {
            $returnType = $this->getType($returnType);
            $returnTypeString = ": {$returnType}";
        }

        $methodContent = "\tpublic function {$method->getName()}({$parameters}){$returnTypeString} {\n";
        $methodContent .= "\t\t\$cacheKey = '';\n";
        foreach ($parametersCall as $parameter) {
            $methodContent .= "\t\t\$cacheKey .= serialize({$parameter});\n";
        }
        $methodContent .= "\t\t\$cacheKey = hash('sha512', \$cacheKey);\n";
        $methodContent .= "\t\t\$cacheKey = \"rikudou_memoize_{$serviceName}_{$method->getName()}_{\$cacheKey}\";\n\n";
        $methodContent .= "\t\t\$cacheItem = \$this->cache->getItem(\$cacheKey);\n";

        $methodContent .= "\t\tif (\$cacheItem->isHit()) {\n";
        if ($returnType !== 'void') {
            $methodContent .= "\t\t\treturn;\n";
        } else {
            $methodContent .= "\t\t\treturn \$cacheItem->get();\n";
        }
        $methodContent .= "\t\t}\n";

        $methodContent .= "\t\t\$cacheItem->set(\$this->original->{$method->getName()}({$parametersCallString});\n";
        $methodContent .= "\t\t\$cacheItem->expiresAfter({}$expiresAfter);\n";
        $methodContent .= "\t\t\$this->cache->save(\$cacheItem);\n\n";

        if ($returnType !== 'void') {
            $methodContent .= "\t\treturn \$cacheItem->get();\n";
        }

        $methodContent .= "\t}";

        return $methodContent;
    }

    private function getNonMemoizedMethod(ReflectionMethod $method): string
    {
        $parameters = implode(', ', $this->getParameterDefinitions($method->getParameters()));
        $parametersCall = implode(', ', $this->getParametersAtCallTime($method->getParameters()));

        $returnTypeString = '';
        if ($returnType = $method->getReturnType()) {
            $returnType = $this->getType($returnType);
            $returnTypeString = ": {$returnType}";
        }

        $methodContent = "\tpublic function {$method->getName()}({$parameters}){$returnTypeString} {\n";

        $methodContent .= "\t\t";
        if ($returnType !== 'void') {
            $methodContent .= "return ";
        }
        $methodContent .= "\$this->original->{$method->getName()}({$parametersCall});\n";

        $methodContent .= "\t}";

        return $methodContent;
    }

    private function shouldMemoize(ReflectionMethod $method): bool
    {
        return (
                $this->getAttribute($method, Memoize::class) !== null
                || $this->getAttribute($method->getDeclaringClass(), Memoize::class) !== null
            )
            && $this->getAttribute($method, NoMemoize::class) === null;
    }

    /**
     * @template T
     * @param class-string<T> $attribute
     * @return T|null
     */
    private function getAttribute(ReflectionMethod|ReflectionClass $target, string $attribute): ?object
    {
        $attributes = $target->getAttributes($attribute);
        if (!count($attributes)) {
            return null;
        }

        return $attributes[array_key_first($attributes)]->newInstance();
    }

    /**
     * @param array<ReflectionParameter> $parameters
     * @return array<string>
     */
    private function getParameterDefinitions(array $parameters): array
    {
        $definitions = [];
        foreach ($parameters as $parameter) {
            $type = $this->getType($parameter);
            $definition = "{$type} ";

            if ($parameter->isVariadic()) {
                $definition .= "...\${$parameter->getName()}";
            } else {
                $definition .= "\${$parameter->getName()}";
                if ($parameter->isDefaultValueAvailable()) {
                    $definition .= " = {$this->getDefaultValue($parameter)}";
                }
            }

            $definitions[] = $definition;
        }

        return $definitions;
    }

    private function getType(ReflectionParameter|ReflectionType $parameter): string
    {
        if ($parameter instanceof ReflectionType) {
            $type = $parameter;
        } else {
            $type = $parameter->getType();
        }
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return (string) $type;
            } elseif (str_starts_with($type, '?')) {
                return "\\{$type->getName()}|null";
            }

            return "\\{$type}";
        }

        if ($type instanceof ReflectionUnionType) {
            $separator = '|';
        } elseif ($type instanceof ReflectionIntersectionType) {
            $separator = '&';
        } else {
            throw new LogicException(sprintf("Unknown type of parameter: %s", get_class($type)));
        }

        $types = array_map(
            fn(ReflectionNamedType $type) => $this->getType($type),
            $type->getTypes(),
        );

        return implode($separator, $types);
    }

    private function getDefaultValue(ReflectionParameter $parameter): string
    {
        if (!$parameter->isDefaultValueAvailable()) {
            throw new LogicException('Cannot get default value for a parameter without one');
        }

        if ($parameter->isDefaultValueConstant()) {
            return "\\{$parameter->getDefaultValueConstantName()}";
        }

        $defaultValue = $parameter->getDefaultValue();

        return $this->dumpVariable($defaultValue);
    }

    private function dumpVariable(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        }

        if (is_int($value) || is_float($value)) {
            return "{$value}";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof UnitEnum) {
            return sprintf("\\%s::%s", get_class($value), $value->name);
        }

        if (is_array($value)) {
            return $this->stringifyArray($value);
        }

        throw new LogicException(sprintf("Unsupported type for dumping given: %s", gettype($value)));
    }

    private function stringifyArray(array $array): string
    {
        if (!count($array)) {
            return '[]';
        }

        $result = '[';

        foreach ($array as $key => $value) {
            $result .= is_string($key) ? "'{$key}'" : $key;
            $result .= ' => ';
            $result .= $this->dumpVariable($value) . ', ';
        }

        $result .= ']';

        return $result;
    }

    /**
     * @param array<ReflectionParameter> $parameters
     * @return array<string>
     */
    #[Pure]
    private function getParametersAtCallTime(array $parameters): array
    {
        $result = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                $result[] = "...\${$parameter->getName()}";
            } else {
                $result[] = "\${$parameter->getName()}";
            }
        }

        return $result;
    }

    private function saveClass(string $classContent, string $className): string
    {
        $targetDir = $this->container->getParameter('rikudou.memoize.target_dir');
        if (!is_dir($targetDir)) {
            if (file_exists($targetDir)) {
                throw new RuntimeException(sprintf("The target directory for memoization ('%s') exists but is not a file", $targetDir));
            }

            if (!mkdir($targetDir, 0744, true)) {
                throw new RuntimeException(sprintf("The target directory for memoization ('%s') could not be created", $targetDir));
            }
        }

        if (!file_put_contents("{$targetDir}/{$className}.php", $classContent)) {
            throw new RuntimeException(sprintf("Could not create memoized proxy at '%s/%s.php'", $targetDir, $className));
        }

        return "{$targetDir}/{$className}.php";
    }
}
