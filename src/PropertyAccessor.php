<?php
namespace XMLMapper;

class PropertyAccessor{

	public function setProperties($object,array $properties){
		foreach($properties as $key=>$value){
			$this->setProperty($object,$key,$value);
		}
	}

	public function setProperty($object,$property,$value){
		$object->{'set'.ucfirst($property)}($value);
	}
}