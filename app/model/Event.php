<?php


class Event
{
	protected $m_Id;
	protected $m_Start;
	protected $m_End;
	protected $m_Price;
	protected $m_Summary;
	protected $m_Description;
	protected $m_Duration;
	protected $m_Breaks = array ();
	
	public function __construct ( $start, $end )
	{
		if ( gettype($start) != 'object' )
			$this -> m_Start = new iCalDate ( strtotime($start) );
		else
			$this -> m_Start = $start;
		if ( gettype($end) != 'object' )
			$this -> m_End = new iCalDate ( strtotime($end) );
		else
			$this -> m_End = $end;
	}
    public function __get($prop) 
    {
        return $this->$prop;
    }
       
    public function __set($prop, $val) 
    {
        $this->$prop = $val;
    }

	public function addBreak ( $array ) {
		$this->m_Breaks [] = $array;
	}

    public function hasBreak () {
    	return (bool)floor($this->m_Duration / 6);
    }    
}