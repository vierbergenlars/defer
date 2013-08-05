<?php

namespace DeferTest;

class Tree implements \Defer\Deferrable
{
    protected $id;
    protected $children;
    protected $parent;

    function __construct($id, Tree $parent, array $children)
    {
        $this->id = $id;
        $this->parent = $parent;
        $this->children = $children;
    }

    /**
     *
     * @load children
     */
    function getChildren()
    {
        return $this->children;
    }

    function getId()
    {
        return $this->id;
    }

    /**
     *
     * @load parent
     */
    function getParent()
    {
        return $this->parent;
    }

    function setParent(Tree $parent)
    {
        $this->parent = $parent;
    }
}
