<?php
require_once('util.class.php');
util::initialize();

if($argc != 3) {
  util::out('Usage: unchunk <input_pattern.csv> <num_chunks>');
  die();
}

$verbose = false;
$infilePattern = $argv[1];
$num_chunk = $argv[2];
$data_out = array();
$line = NULL;
$first = true;
$header = NULL;
util::out('reading csv data' );
for($i = 0; $i < $num_chunk; $i++)
{
  $infileName = sprintf($infilePattern, $i);
  if(!file_exists($infileName))
  {
    util::out('file ' . $infileName . ' does not exist');
    die();
  }
  util::out('reading ' . $infileName);
  $file = fopen($infileName,'r');
  if(false === $file)
  {
    util::out('file ' . $infileName . ' cannot be opened');
    die();
  }
  $line_count = 0;
  while(false !== ($line = fgets($file)))
  {
    $line_count++;
    $inline = trim($line, "\n\"\t");
    $inline = explode('","', $inline);
    if($first)
    {
      $header = $inline;
      $first = false;
      continue;
    }
    else
    {
      if(1 == $line_count)
        continue;
    }

    if(count($header) != count($inline))
    {
      util::out('Error: line (' . $line_count . ') wrong number of elements ' . util::flatten($inline));
      continue;
    }
    $data_out[] = $line;
  }
  fclose($file);
}

$fname = str_replace('%d', 'COMBINED', $infilePattern);
util::out('writing ' . count($data_out) . ' lines to ' . $fname);
$file = fopen($fname,'w');
$header = '"' .  util::flatten($header,'","') . '"' . PHP_EOL;
fwrite($file,$header);
foreach($data_out as $line)
{
  fwrite($file, $line);
}
fclose($file);

?>
