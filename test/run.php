<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/simpletest/simpletest/autorun.php';


$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->add('DeferTest', __DIR__);
$classLoader->register();

class DeferTests extends \UnitTestCase
{
    private $struct = array(
        1=>array(2,3,4),
        2=>array(5),
        3=>array(8),
        4=>array(7,6),
        5=>array(1,9),
        6=>array(),
        7=>array(),
        8=>array(),
        9=>array(),
    );
    function __construct() {
        \Defer\Object::$DEFAULT_CACHE_DIR = '/tmp';
        \Defer\Object::$DEFAULT_PREFIX = '__TestCG__';
        parent::__construct();
    }

    function testReference()
    {
        $loader = new \DeferTest\Loader($this->struct);
        $ref = new \Defer\Reference($loader, 1);
        $instance = $ref->loadRef();

        $this->assertIsA($instance, 'DeferTest\\Tree');
        $this->assertIsA($instance->getParent(), 'DeferTest\\Tree');
        $this->assertEqual($instance->getParent()->getId(), 5);
        $children = $instance->getChildren();
        foreach($children as $child)
            $this->assertIsA($child, 'DeferTest\\Tree');
        $this->assertEqual($children[0]->getId(), 2);
        $this->assertEqual($children[1]->getId(), 3);
        $this->assertEqual($children[2]->getId(), 4);

    }

    function testDefer()
    {
        $loader = new \DeferTest\Loader($this->struct);
        $parent_ref = new Defer\Reference($loader, 5);
        $children_refs = array();
        foreach($this->struct[1] as $child)
            $children_refs[] = new Defer\Reference($loader, $child);
        $instance = Defer\Object::defer(array('id'=>1, 'parent'=> $parent_ref, 'children'=>$children_refs), 'DeferTest\\Tree');

        $this->assertIsA($instance, 'DeferTest\\Tree');
        $this->assertIsA($instance->getParent(), 'DeferTest\\Tree');
        $this->assertEqual($instance->getParent()->getId(), 5);
        $children = $instance->getChildren();
        foreach($children as $child)
            $this->assertIsA($child, 'DeferTest\\Tree');
        $this->assertEqual($children[0]->getId(), 2);
        $this->assertEqual($children[1]->getId(), 3);
        $this->assertEqual($children[2]->getId(), 4);
    }

    function testBug1Regression()
    {
        $loader = new \DeferTest\Loader($this->struct);
        $parent_ref = new Defer\Reference($loader, 5);
        $children_refs = array();
        foreach($this->struct[1] as $child)
            $children_refs[] = new Defer\Reference($loader, $child);
        $instance = Defer\Object::defer(array('id'=>1, 'parent'=> $parent_ref, 'children'=>$children_refs), 'DeferTest\\Tree');

        $this->assertEqual($instance->getId(), 1);
    }

}
