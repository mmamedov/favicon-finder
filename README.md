Favicon Finder
---
Attempts to find favicon given a domain name or directly from Alexa's top ranked domains CSV file.


How to Use
--
App runs from command line. See configuration options in `config/params.php` file.

### Single domain lookup
```
php app.php example.com
```

### Top Alexa domains lookup
Unzip [Alexa's top domains](http://s3.amazonaws.com/alexa-static/top-1m.csv.zip) file into `input/` directory.
File is large, but each worker will only load it's processing porting of the file.   

Script below spawns 200 PHP processes in the background each with 1000 domains to lookup (400 processes with 500 domains when `$doubleWorkers` set to `true` ),
in order to get favicons for the Alexa top 200k ranked URLs file. 
<br>
This is a low-intensity CPU task, with each process using ~10MB of RAM and receiving in total ~2MB of network data.   

Each process has it's own log file and CSV output file, stored in `output/worker_csv` and `output/worker_log` directories. 
Additionally each worker will save it's runtime stats in `output/workers.log`.
 
Please note that this might take several hours depending on your machine and network speed. 
Usually with a good connection processes complete in 1-2 hours. 

```
php init_200k_worker.php 1
```

<br>
Check number of PHP processes running. 
This should return a bit more than 400, as it will also capture grep command, and anything else matching to php in the output.

```
ps aux | grep -c php
```

<br>
Check number of domains processed so far and saved in CSV files

```
wc -l output/worker_csv/*.csv
```

<br>
In the end concatenate all CSV files into a single CSV file. This file should have 200k rows

```
cat output/worker_csv/*.csv > all.csv
```

<br>
To include in your application using composer:
```
composer require mmamedov/favicon-finder
```

To run from command line, clone this repo and run 
```
composer install -o
```


#### Prerequisites
- PHP 7.3 or higher (7.3 specific CURL options were used, i.e. `CURLINFO_SCHEME`)
- Latest CURL / SSL libs



How it works
---
FaviconFinder uses `Inspectors` to lookup favicons. 
Currently there are 2 Inspectors implemented, they are called one after another, if previous Inspector fails to find result.

### `HeadersInspector` 
Looks at HTTP response headers, and visits redirects in the `Location` heaeder as necessary. 
Starts with `https://<domain>/favicon.ico` location, as this is the most likely location.

## `HtmlInspector`
Downloads HTML of the domain, and looks for variations of `<link rel>` tag for favicon location.
It uses CURL library and follows redirects to reach to actual page. 