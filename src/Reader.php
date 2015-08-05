<?php
namespace XMLMapper;

class Reader{

	/*
	 $map = [
	'grid' => [
		'class' => '\Ext\Grid\Panel',
		'childProperty' => 'items'
	],
	'column' => [
		'class' => function(&$config){
			$columnBase = '\Ext\Grid\Column\\';
			$class = $columnBase.$config['type'];
			unset($config['type']);
			return new $class;
		}
	],
	'store' => '\Ext\Data\Store',
];
	 */
	private $map=[];

	/** @var  PropertyAccessor */
	private $propertyAccessor;

	private $xml;

	private $propertyNamespace = 'p';

	private $variables = [];

	/**
	 * @return PropertyAccessor
	 */
	public function getPropertyAccessor()
	{
		return $this->propertyAccessor;
	}

	/**
	 * @param PropertyAccessor $propertyAccessor
	 */
	public function setPropertyAccessor(PropertyAccessor $propertyAccessor)
	{
		$this->propertyAccessor = $propertyAccessor;
	}

	/**
	 * @return array
	 */
	public function getVariables()
	{
		return $this->variables;
	}

	/**
	 * @param array $variables
	 */
	public function setVariables(array $variables)
	{
		$this->variables = $variables;
	}

	public function setVariable($name,$value){
		$this->variables[$name] = $value;
	}


	/**
	 * @return array
	 */
	public function getMap()
	{
		return $this->map;
	}

	/**
	 * @param array $map
	 */
	public function setMap($map)
	{
		$this->map = $map;
	}

	public function setXml($xml){
		$this->xml = $xml;
	}


	public function parse(){
		$xml=$this->xml;

		if(is_string($xml)) {
			$xml = new \SimpleXMLElement($this->xml);
		}

		return $this->parseNode($xml);

	}

	protected function parseCollection(\SimpleXMLElement $xml){
		$result = [];
		foreach ($xml->children() as $node ) {
			$result[] = $this->parseNode($node);
		}
		return $result;
	}

	protected function parseNode(\SimpleXMLElement $node){
		$tag = (string)$node->getName();
		$tagMap = $this->getTagMap($tag);
		if(!$tagMap){
			return false;
		}
		$attributes = $this->getNodeAttributes($node);
		$object = $this->getTagObject($tagMap,$attributes);

		$this->getPropertyAccessor()->setProperties($object,$attributes);

		if($node->children()){
			if($children = $this->parseCollection($node)){
				$this->getPropertyAccessor()->setProperty($object,$tagMap['childProperty'],$children);
			}
		}

		return $object;

	}

	protected function getTagObject(array $tagMap,&$attributes){

		if(is_callable($tagMap['class'])){
			return call_user_func_array($tagMap['class'],[&$attributes,$this->getVariables()]);
		}else{
			$class = $tagMap['class'];
			return new $class;
		}
	}

	protected function getTagMap($tag){
		if(array_key_exists($tag,$this->map)){
			$tagMap = $this->map[$tag];
		}elseif(array_key_exists('*',$this->map)){
			$tagMap = $this->map['*'];
		}else{
			return false;
		}

		if(is_string($tagMap)){
			$tagMap = ['class'=>$tagMap];
		}

		return $tagMap;
	}

	protected function unserializeProperty($value){
		if(strpos($value,'json:')===0){
			return json_decode(mb_substr($value,5),true);
		}
		return $value;
	}

	protected function getNodeAttributes(\SimpleXMLElement $node){
		$attributes = [];

		if($node->attributes()){
			$attributes = current($node->attributes());
		}
		//Try namespaced attributes
		foreach($node->children($this->propertyNamespace, true) as $property){
			/** @var \SimpleXMLElement $property */
			if($property->children()){
				$propertyValue = $this->parseCollection($property);
				//Use property discover for array, use 'array' attribute to force array value
				if(count($propertyValue)==1 and !(bool)$property->attributes()->array){
					$propertyValue = current($propertyValue);
				}

			}else{
				$propertyValue = (string)$property;
			}

			$attributes[(string)$property->getName()] = $propertyValue;
		}


		foreach($attributes as &$attribute){
			if(is_string($attribute)){
				$attribute = $this->unserializeProperty($attribute);
			}
		}

		return $attributes;
	}

}