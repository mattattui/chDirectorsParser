chDirectorsParser
=================

A PHP 5.2 class for parsing UK Companies House appointment data files. The implementation deviates slightly from the specification where the spec doesn't match reality.

Usage
-----

```php
require('chDirectorsParser.class.php');

$parser = new chDirectorsParser('sample-data/Prod195_9938_ew_3_Sample.dat');
print $parser.PHP_EOL;

foreach($parser as $entry)
{
    if ($entry instanceof chCompany)
    {
        printf(PHP_EOL."Company: %s (%s: %s)".PHP_EOL, $entry->name, $entry->id, $entry->status);
    } 
    else 
    {
        printf("\tName: %s %s %s %s".PHP_EOL, $entry->details['title'], $entry->details['forenames'], $entry->details['surname'], $entry->details['honours']);
        printf("\tCompanies House ID: %s".PHP_EOL, $entry->id);
        printf("\tPosition: %s".PHP_EOL, $entry->appointment_type);
        print PHP_EOL;
    }
    
}
```

Company properties
------------------

* id - Companies House company number
* status - 'Standard', 'Converted/closed', 'Dissolved', 'In liquidation', 'In receivership'
* officers - Number of officers
* name - Company name


Person properties
-----------------

* `company_id` – company number
* `appointment_date_origin_code` – 1-5
* `appointment_date_origin` – 'Appointment document', 'Annual return', 'Incorporation document', 'LLP appointment document', 'LLP incorporation document'
* `appointment_type_code` – '00', '01', '04', '05', '11', '12', '13'
* `appointment_type` – 'Secretary', 'Director', 'Non-designated LLP Member', 'Designated LLP Member', 'Judicial Factor', 'Receiver or Manager (Charities Act)', 'Manager (CAICE Act)'
* `id` – person number
* `revision` – revision of this person's data
* `corporate` – false for individuals, true for corporate entities
* `appointment_date` – DateTime object
* `resignation_date` – DateTime object
* `postcode` – string
* `date_of_birth` – DateTime object
* `details` – name and address:
    * `title`
    * `forenames`
    * `surname`
    * `honours`
    * `care_of`
    * `po_box`
    * `address_1`
    * `address_2`
    * `town`
    * `county`
    * `country`
    * `occupation`
    * `nationality`
    * `residence`



Todo
----

* Refactor as PHP stream?
* Tests
* Allow factory to instantiate customised objects, instead of only chPerson/chCompany (e.g. ORM objects)
* Replace fixed types (e.g. appointment origin) with class constants?
* Don't use magic `__get()/__set()`
