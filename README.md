WatchTowerDB-CI-Logging
=======================

WatchTowerDB is the Database driven version of [WatchTower Logging](https://github.com/JamesMcFall/WatchTower-CI-Logging). It is even simpler to set up and more useful as errors are logged in the database and categorised by "streams" which are explained below.

WatchTowerDB is a CodeIgniter library for writing errors to the database in **streams** (not unlike individual files i.e. "login-attempts" or "catastrophic-planet-destroying-errors") which make logging large amounts of data possible while still being practical to search though.

As some log entries require immediate action there is the ability to notify someone of a particular type of error (i.e. in a specific stream) is possible. This is very useful if different people need to be notified of issues in different parts of an application.


## Installation
### The Library
Simply copy the _WatchTowerDB.php_ library file to your _application/libraries_ folder. From there you'll want to add it to the autoloader ( _application/config/autoload.php_ ) in the libraries array.

```php
$autoload['libraries'] = array("WatchTowerDB");
```

### Configuration
No further configuration is needed! The rest is automagic.

## Usage
Once that initial setup is done, logging becomes very very simple.

### Basic logging
Logging is very straightforward. The first parameter specifies which log/stream you want to write into. The second parameter is the message to put in the log stream.
```php
# Note the first parameter is the logging stream (ie an individual log)
$this->watchtowerdb->log("registration", "Oh no. Steve tried to enrol again. Don't worry, we stopped him!");
```

### Sending a notification email out when an error is logged.
If you want to notify someone when there is an error posted to a stream, add them in the database for that streamm then supply a third parameter of true.

```php
# Note the third parameter is now "true". This is the "Notify" parameter.
$this->watchtowerdb->log("registration", "Oh no. Steve tried to enrol again. Don't worry, we stopped him!", true);
```

### Dumping debugging data into the log
In some instances you'll likely be wanting to dump an array or object into a log. A method has been added into the logger to make this easy.

```php
$o = new stdClass();
$o->one   = 1;
$o->two   = 2;
$o->three = 3;
            
$this->watchtowerdb->log("registration", "Log an object: " . $this->watchtower->dumpVarToString($o));
```
