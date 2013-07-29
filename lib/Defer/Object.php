<?php
/**
 * Copyright (c) 2013 Lars Vierbergen
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Defer;

/**
 * Defer the loading of an object
 */
class Object
{
    /**
     * The default value for the class prefix.
     * @var string
     */
    static public $DEFAULT_PREFIX = '__CG__';
    /**
     * The default value for the generated class cache directory.
     * @var string
     */
    static public $DEFAULT_CACHE_DIR = __DIR__;
    /**
     * Toggle development mode (regenerates classes every time)
     * @var boolean
     */
    static public $DEVELOPMENT_MODE = false;
    /**
     * The data to insert in the object
     * @var array
     */
    private $data;
    /**
     * Reflection of the object
     * @var \ReflectionClass
     */
    private $reflection;

    /**
     * Prefix for generated classes
     * @var string
     */
    private $prefix;

    /**
     * The directory where generated classes get cached
     * @var string
     */
    private $cacheDir;

    /**
     * Creates a new defer object
     * @param array  $data   The data to set the properties to, as an array
     * @param string $object The full class name of the class to load. The class must implement Deferrable
     * @param string $prefix The prefix for all generated classes
     * @param string $cacheDir The directory where generated classes get cached
     * @throws \LogicException Thrown when the prefix is invalid, or the class does not implement {@link Deferrable}
     * @throws \RuntimeException Thrown when the cache directory is not writable
     */
    public function __construct($data, $object, $prefix = null, $cacheDir = null)
    {
        if($prefix === null)
            $prefix = self::$DEFAULT_PREFIX;
        if($cacheDir === null)
            $cacheDir = self::$DEFAULT_CACHE_DIR;

        if(substr($prefix,-1) == '\\')
            throw new \LogicException('The class prefix ('.$prefix.') should not end in a \\');
        if($prefix[0] == '\\')
            throw new \LogicException('The class prefix ('.$prefix.') should not start with a \\');
        if(!is_dir($cacheDir)||!is_writable($cacheDir))
            throw new \RuntimeException('The cache directory ('.$cacheDir.') is not writable');

        $this->data = $data;
        $this->prefix = $prefix;
        $this->cacheDir = $cacheDir;
        $this->reflection = new \ReflectionClass($object);
        if(!$this->reflection->implementsInterface(__NAMESPACE__.'\\Deferrable'))
            throw new \LogicException($object.' should implement Deferrable');
    }

    /**
     * Injects the data in the class
     * @param  Deferrable $object The object to inject the data in
     * @param array $props The properties to inject in the class
     * @return Deferrable The instanciated class with its loaded properties
     */
    public function injectData(Deferrable $object = null, array $props = null)
    {
        if ($object !== null) {
            $reflection = new \ReflectionClass($object);
            if ($reflection->getParentClass()->name !== $this->reflection->name) {
                throw new \LogicException('You can only inject data in a class of the same type');
            }
            $class = $object;
        } else {
            $reflection = $this->reflection;
            $class = $this->reflection->newInstance($this);
        }
        foreach ($this->data as $key=>$value) {
            // Don't load stuff not in $props, if it is set
            if($props !== null && !in_array($key, $props)) continue;
            $prop = $reflection->getProperty($key);
            $prop->setAccessible(true);
            // Only load variable when it is not yet loaded
            if($prop->getValue($class) === null) {
                // When the value given only is a reference, load it.
                if ($value instanceof Reference) {
                    $value = $value->loadRef();
                } elseif (is_array($value)) {
                    $value = self::injectDataInArray($value);
                }
                $prop->setValue($class, $value);
            }
            $prop->setAccessible(false);
        }

        return $class;
    }

    /**
     * Recursively injects References in an array
     * @param  array $array The array to parse
     * @return array The array with references resolved
     */
    private static function injectDataInArray($array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::injectDataInArray($value);
            } elseif ($value instanceof Reference) {
                $value = $value->loadRef();
            } else {
                continue;
            }
        }

        return $array;
    }

    /**
     * Generates the deferred class to a subclass that invokes the data injection
     * @return object The instanciated generated class
     */
    private function generateClass()
    {
        $name = $this->reflection->getName();
        $filename = $this->cacheDir.'/'.str_replace('\\', '-', $name).'.php';

        if (!file_exists($filename)||self::$DEVELOPMENT_MODE) {

            $tpl_params = '%s %s$%s %s,';
            $tpl_params_call = '$%s,';
            $tpl_methods = 'function %s(%s) {
                if (!isset($this->loaded[\'%1$s\'])) {
                    $this->defer->injectData($this, %s);
                    $this->loaded[\'%1$s\'] = true;
                }

                return parent::%1$s(%s);
            }';

            $tpl_code = '<?php namespace %s\\%s;
            class %s extends \\%2$s\\%3$s {
                private $loaded = array();
                private $defer;
                function __construct($defer)
                {
                    $this->defer = $defer;
                }
                %s
            }';

            $methods = $this->reflection->getMethods();

            $methods_code = '';
            foreach ($methods as $method) {
                if($method->isConstructor()) continue;
                if($method->isStatic()) continue;

                $props_to_load = self::parseDocComment($method);
                if($props_to_load === null) continue;

                $parameter_code = '';
                $parameter_call_code = '';
                $parameters = $method->getParameters();
                foreach ($parameters as $parameter) {
                    $type = '';
                    $type_class = $parameter->getClass();
                    if ($type_class !== null) {
                        $type = $type_class->getName();
                        if($type != '') $type = '\\'.$type;
                    }
                    $default_val = '';
                    if ($parameter->isDefaultValueAvailable()) {
                        $default_val = '='.var_export($parameter->getDefaultValue(), true);
                    }
                    $ref = '';
                    if ($parameter->isPassedByReference()) {
                        $ref = '&';
                    }
                    $pname = $parameter->getName();
                    $parameter_code.=sprintf($tpl_params, $type, $ref, $pname, $default_val);
                    $parameter_call_code .= sprintf($tpl_params_call, $pname);
                }
                $parameter_code = substr($parameter_code, 0, -1);
                $parameter_call_code = substr($parameter_call_code,0,-1);

                $mname = $method->getName();
                $methods_code.=sprintf($tpl_methods, $mname, $parameter_code, var_export($props_to_load, true), $parameter_call_code);

            }

            $code =sprintf($tpl_code,
                    $this->prefix,
                    $this->reflection->getNamespaceName(),
                    $this->reflection->getShortName(),
                    $methods_code);

            file_put_contents($filename, $code);
        }
        require_once $filename;
        $hackreflection = new \ReflectionClass($this->prefix.'\\'.$name);
        $hack = $hackreflection->newInstance($this);

        return $hack;
    }

    private static function parseDocComment(\ReflectionMethod $method)
    {
        $docComment = $method->getDocComment();
        $startLoad = strpos($docComment, '@load');
        if($startLoad === false) return null;
        $endLoad = strpos($docComment, "\n", $startLoad);
        $loadPropsTag = substr($docComment, $startLoad, $endLoad-$startLoad);
        $loadProps = explode(' ', $loadPropsTag);
        array_shift($loadProps);

        return $loadProps;
    }

    public function getClass()
    {
        return $this->generateClass();
    }

    public static function defer($data, $object, $prefix = null, $cacheDir = null)
    {
        $defer = new self($data, $object, $prefix, $cacheDir);

        return $defer->getClass();
    }
}
