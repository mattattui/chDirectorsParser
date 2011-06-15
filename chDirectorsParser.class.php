<?php

class chDirectorsParser implements Iterator
{
  protected $filehandle    = null;
  protected $filename      = null;
  protected $line_number   = null;
  protected $header_length = null;
  protected $footer        = null;
  protected $current_line  = null;
  protected $eof           = null;
  protected $entry_count   = null;
  
  public $run_number       = null;
  public $production_date  = null;
  
  
  public function __construct($file)
  {
    $this->filehandle = fopen($file, 'rb');
    $this->filename   = $file;
    
    // Check header
    $header = fgets($this->filehandle, 128);
    $this->header_length = strlen($header);
    
    fseek($this->filehandle, -17, SEEK_END);
    $this->footer = fread($this->filehandle, 128);
    
    if (!preg_match('/^DDDDSNAP(\d{4})(\d{4}\d{2}\d{2})$/', $header, $matches))
    {
      throw new Exception('Invalid file header.');
    }

    $this->eof = false;
    $this->run_number = intval($matches[1]);
    $this->production_date = chDirectorsEntry::decodeDate($matches[2]);
    
    if (!preg_match('/^99999999(\d{8})$/', $this->footer, $matches))
    {
      throw new Exception('Missing or invalid footer');
    }
    
    $this->entry_count = intval($matches[1]);
    
    
  }
  
  public function __toString()
  {
    $d = $this->getInfo();
    return sprintf(
      'Appointment data #%d generated on %s. %d records. Filename: %s',
      $d['production_number'],
      $d['production_date'],
      $d['record_count'],
      $d['filename']
    );
    
  }
  public function getInfo()
  {
    return array(
      'filename'          => $this->filename,
      'production_date'   => $this->production_date->format('Y-m-d'),
      'production_number' => $this->run_number,
      'record_count'      => $this->entry_count,

    );
    
  }





  // Iterator declarations

  public function current() { return $this->current_line; }
  public function key()     { return $this->line_number; }
  public function valid()   { return !$this->eof; }
  
  public function next()
  {
    $row = fgets($this->filehandle, 1024);
    if ($row == $this->footer)
    {
      $this->eof = true;
      return;
    }
    
    $obj = chDirectorsEntry::factory($row);
    
    
    $this->current_line = $obj;
    $this->line_number++;
  }
  
  public function rewind()
  {
    $this->current_line = null;
    $this->line_number = 0;
    fseek($this->filehandle, $this->header_length);
    
    $this->next();
  }


  // Close the filehandle when killing the object
  public function __destruct()
  {
    fclose($this->filehandle);
  }

}



class chDirectorsEntry
{
  protected $data = array();
  
  
  protected function __construct($row = null)
  {
    if ($row)
    {
      $this->parse($row);
    }
  }
  
  public function __get($var)
  {
    if (isset($this->data[$var]))
    {
      return $this->data[$var];
    } else {
      throw new Exception('Unknown property');
    }
  }
  
  public function __set($var, $value)
  {
    if (array_key_exists($var, $this->data))
    {
      $this->data[$var] = $value; 
    } else {
      throw new Exception('Unexpected property');
    }
  }
  
  public static function factory($row)
  {
    $record_type = substr($row,8,1);
    if (1 == $record_type) // Company record
    {
      return new chCompany($row);
    } elseif (2 == $record_type) // Person
    {
      return new chPerson($row);
    }
  }
  
  


  protected function decodeCompanyType($type)
  {
    switch($type)
    {
      case ' ': return 'Standard';
      case 'C': return 'Converted/closed';
      case 'D': return 'Dissolved';
      case 'L': return 'In liquidation';
      case 'R': return 'In receivership';
    }
    
  }
  
  
  static public function decodeDate($date)
  {
    if (!trim($date)) { return false; }
    return date_create(sprintf('%04d-%02d-%02d', substr($date,0,4), substr($date,4,2), substr($date,6,2)));
    
  }
  
  
  static public function decodeAppointmentOrigin($o)
  {
    switch($o)
    {
      case 1: return 'Appointment document';
      case 2: return 'Annual return';
      case 3: return 'Incorporation document';
      case 4: return 'LLP appointment document';
      case 5: return 'LLP incorporation document';
    }
  }
  
  static public function decodeAppointmentType($o)
  {
    switch($o)
    {
      case '00': return 'Secretary';
      case '01': return 'Director';
      case '04': return 'Non-designated LLP Member';
      case '05': return 'Designated LLP Member';
      case '11': return 'Judicial Factor';
      case '12': return 'Receiver or Manager (Charities Act)';
      case '13': return 'Manager (CAICE Act)';
    }
  }


  static public function decodePersonalData($personal)
  {
    $fieldnames = array(
      'title',
      'forenames',
      'surname',
      'honours',
      'care_of',
      'po_box',
      'address_1',
      'address_2',
      'town',
      'county',
      'country',
      'occupation',
      'nationality',
      'residence',
      );
    $parts = explode('<', $personal);
    array_pop($parts); // Last item is blank
    
    if (count($parts) != 14)
    {
      $data = array_combine(array_slice($fieldnames,0,count($parts)), $parts);
      foreach(array_slice($fieldnames,count($parts)) as $blank_key)
      {
        $data[$blank_key] = '';
      }
      
    } else {
      $data = array_combine($fieldnames, $parts);
    }
    
    return $data;
  }
  
}


class chPerson extends chDirectorsEntry
{
  protected $data = array(
    'company_id'                   => null,
    'appointment_date_origin_code' => null,
    'appointment_date_origin'      => null,
    'appointment_type_code'        => null,
    'appointment_type'             => null,
    'id'                           => null,
    'revision'                     => null,
    'corporate'                    => null,
    'appointment_date'             => null,
    'resignation_date'             => null,
    'postcode'                     => null,
    'date_of_birth'                => null,
    'details'                      => null,
  );
  
  
  public function parse($row)
  {
      //                  company#    P ADOC   Type                  P#      Corp       App date    Res date    Pcode   DOB      AddrLen Address data
      if (!preg_match('/^([0-9A-Z]{8})2([1-6])(00|01|04|05|11|12|13)(\d{12})([ Y])\s{7}(\d{8}| {8})(\d{8}| {8})(.{8})(.{8})(\d{4})(.+)/u', $row, $matches))
      {
        throw new Exception('Invalid person record:'.PHP_EOL.'  ['.$row.']');
      }

      $this->company_id                   = $matches[1];
      $this->appointment_date_origin_code = $matches[2]; 
      $this->appointment_date_origin      = chDirectorsEntry::decodeAppointmentOrigin($matches[2]);
      $this->appointment_type_code        = $matches[3];
      $this->appointment_type             = chDirectorsEntry::decodeAppointmentType($matches[3]);
      $this->id                           = substr($matches[4],0,-4); // split into person id and revision number
      $this->revision                     = substr($matches[4],-4);
      $this->corporate                    = $matches[5] == 'Y' ? true : false;
      $this->appointment_date             = chDirectorsEntry::decodeDate($matches[6]);
      $this->resignation_date             = chDirectorsEntry::decodeDate($matches[7]);
      $this->postcode                     = $matches[8];
      $this->date_of_birth                = chDirectorsEntry::decodeDate($matches[9]);
      $this->details                      = chDirectorsEntry::decodePersonalData(substr($matches[11],0,intval($matches[10])));

    
  }
}

class chCompany extends chDirectorsEntry
{
  protected $data = array(
    'id'       =>null,
    'status'   =>null,
    'officers' =>null,
    'name'     =>null,
    );

  
  
  public function parse($row) {
    if (!preg_match('/^([0-9A-Z]{8})1([CDLR ])\s{22}(\d{4})\d{4}([^<]+)</u', $row, $matches))
    {
      throw new Exception('Invalid company record:'.PHP_EOL.'  ['.$row.']');
    }
  
    $this->id       = $matches[1];
    $this->status   = chDirectorsEntry::decodeCompanyType($matches[2]);
    $this->officers = intval($matches[3]);
    $this->name     = $matches[4];
  }
}



if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__))
{
  if (!isset($_SERVER['argv']) || !isset($_SERVER['argv'][1]))
  {
    die('Provide a filename to read');
  }
 
  $obj = new chDirectorsParser($_SERVER['argv'][1]);
  print_r($obj->getInfo());

  
  // foreach($obj as $key => $entry)
  // {
  //   print $key.':';
  //   print_r($entry);
  // }
}