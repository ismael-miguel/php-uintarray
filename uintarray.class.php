<?php

final class UintArray implements \Countable,\ArrayAccess,\IteratorAggregate
{
	private $data = array();   //all the saved data will be here
	private $maximum_length = 0;  //maximum length of the array
	//current count, can't use $count because accessing $this->count might give troubles
	private $current_count = 0; 

	//numbers of bytes to store
	const UInt8 = 1;
	const UInt16 = 2;
	const UInt24 = 3;
	const UInt32 = 4;
	const UIntDefault = self::UInt32; //default value

	//the index is kept here only for readability.
	private static $bit_masks=array(
		self::UIntDefault => 0x7FFFFFFF, //default bit mask
		self::UInt8 => 0xFF,
		self::UInt16 => 0xFFFF,
		self::UInt24 => 0xFFFFFF,
		//highest possible unsigned 32-bit integer
		self::UInt32 => 0x7FFFFFFF
	);

	//used to be sure the value doesn't go above the maximum value
	private $bit_mask;

	private static function sanitize($value, $bit_mask = 0){
		//sanitize the value, to ensure it is less or equal to the desired mask
		return $value & ( $bit_mask ? $bit_mask : self::$bit_masks[self::UInt32]);
	}

	public function __construct($maximum_length, $bytes_per_element = 0){
		//set the length to a 32bit integer
		$maximum_length = self::sanitize($maximum_length);

		//stores the maximum length, check if it higher than 0
		$this->maximum_length = ( $maximum_length > 0 ) ? $maximum_length : 1;

		//sets the bit mask to be used
		$this->bit_mask = self::$bit_masks[ ( $bytes_per_element >= 1 && $bytes_per_element <= 4 ) ? $bytes_per_element : self::UIntDefault];

		//fill the array ahead, so its space will be all reserved
		//in theory, this will be faster than creating elements each time
		$this->data = array_fill(0, $this->maximum_length, 0);
	}

	//countable
	public function count(){
		return $this->current_count;
	}

	//arrayaccess
	public function offsetSet($offset, $value){
		$this->__set($offset, $value);
	}
	//used with isset($arr[<offset>]);
	public function offsetExists($offset){
		$offset = self::sanitize($offset);

		//if the offset is within the limits
		if($offset > 0 && $offset <= $this->maximum_length)
		{
			return isset($this->data[$offset]);
		}
		return false;
	}
	//used with unset($arr[<offset>]);
	public function offsetUnset($offset){
		$offset = self::sanitize($offset);

		//if the offset is withing the limits
		if($offset > 0 && $offset <= $this->maximum_length)
		{
			$this->data[$offset]=0;

			if($offset == $this->current_count-1)
			{
				//if we are unsetting the last element, we can safely reduce the count
				--$this->current_count;
			}
		}
	}
	//used with $arr[<offset>];
	public function offsetGet($offset){
		return $this->__get($offset);
	}

	//iteratoraggregate
	//used on the foreach loop
	public function getIterator(){
		return new ArrayIterator($this->toArray());
	}

	//magic methods
	public function __toString(){
		//replicated the default behavior of converting an array to string
		return array() . '';
	}
	public function __invoke(){
		return $a=&$this->data;
	}
	public function __set_state(){
		return $a=&$this->data;
	}
	public function __set($offset, $value){
		//allows to set $arr[]=<value>;
		if(is_null($offset))
		{
			//verifies if the array is full. returns false if it is.
			if($this->current_count >= $this->maximum_length)
			{
				return false;
			}

			//provides the offset to set the value
			$offset = $this->current_count++;
		}
		//verifies if the $offset is within the allowed limits
		else if( $offset < 0 || $offset > $this->maximum_length)
		{
			return false;
		}

		$this->data[ self::sanitize($offset) ] = self::sanitize($value, $this->bit_mask);
		
		if( $this->current_count >= $offset )
		{
			$this->current_count = $offset + 1;
		}
	}
	public function __get($offset){
		$offset = self::sanitize($offset);

		//returns a dummy variable, just in case someone uses the increment (++) or decrement (--) operators
		$dummy = isset($this->data[$offset]) ? $this->data[$offset] : null;
		return $dummy;
	}
	public function __sleep(){
		return $this->data;
	}

	//other functionality methods
	public function push(){   
		//retrieve all the arguments, saving one variable
		foreach(func_get_args() as $value)
		{
			//if the array is full, exit the loop
			if( $this->current_count >= $this->maximum_length )
			{
				break;
			}
			
			//add to the array, increasing the count
			$this->data[ $this->current_count++ ] = self::sanitize($value, $this->bit_mask);
		}

		//returns the number of elements
		//this replicated the behaviour of the function array_push()
		//Documentation: http://php.net/manual/en/function.array-push.php
		//Test-case (using array_push()): http://ideone.com/PrTo8m
		return $this->current_count;
	}

	public function pop(){
		//if the array is empty
		if($this->current_count < 1)
		{
			return null;
		}
		
		//decreases the count and stores the last value
		$value = $this->data[ --$this->current_count ];

		//stores 0 on the last value
		$this->data[ $this->current_count ]=0;

		//returns the last element
		return $value;
	}

	public function maxlen(){
		return $this->maximum_length;
	}
	public function bitmask(){
		return $this->bit_mask;
	}
	public function toArray(){
		return array_slice($this->data, 0, $this->current_count);
	}
}
