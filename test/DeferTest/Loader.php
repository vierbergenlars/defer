<?php

namespace DeferTest;

class Loader implements \Defer\Loader
{
    /**
     *
     * @var array
     */
    private $struct;

    /**
     *
     * @param array $struct
     */
    public function __construct(array $struct) {
        $this->struct = $struct;
    }

    /**
     *
     * @param string|int $id
     * @return Tree
     */
    public function load($id)
    {
        $children_ids = $this->struct[$id];
        $children_refs = array();
        foreach($children_ids as $child_id) {
            $children_refs[] = new \Defer\Reference($this, $child_id);
        }
        foreach($this->struct as $possible_parent=>$children) {
            if(array_search($id, $children) !== false)
                    $parent_id = $possible_parent;
        }
        $parent_ref = new \Defer\Reference($this, $parent_id);
        return \Defer\Object::defer(
                array(
                    'id' => $id,
                    'children' => $children_refs,
                    'parent' => $parent_ref
                ), __NAMESPACE__.'\\Tree');
    }
}
