<?php
require_once('util.class.php');
util::initialize();

if($argc != 3) {
  util::out('Usage: chunk <input.csv> <num_chunks>');
  die();
}

$verbose = false;
$infileName = $argv[1];
$num_chunk = $argv[2];
$data_in = array();
$file = fopen($infileName,'r');
if(false === $file)
{
  util::out('file ' . $infileName . ' cannot be opened');
  die();
}

$line = NULL;
$line_count = 0;
$first = true;
$header = NULL;
util::out('reading csv data' );
while(false !== ($line = fgets($file)))
{
  $line_count++;
  $inline = trim($line, "\n\"\t");
  $inline = explode('","',$inline);
  if($first)
  {
    $header = $inline;
    $first = false;
    continue;
  }

  if(count($header)!=count($inline))
  {
    util::out('Error: line (' . $line_count . ') wrong number of elements ' . util::flatten($inline)  );
    continue;
  }
  $data_in[] = $line;
}
fclose($file);

$target = count($data_in);
util::out('read ' . $target . ' lines' );

$line_chunk = intval(floor($target/$num_chunk));
$num_lines = $line_chunk*$num_chunk;
$remain = $target - $num_lines;
$remain = $remain > 0 ? $remain : 0;
$file_lines = array();
for($i = 0; $i < $num_chunk; $i++) $file_lines[] = $line_chunk;
if(0 != $remain) $file_lines[] = $remain;
$num_chunk = count($file_lines);

util::out('chunking out ' .  ($num_lines + $remain) . ' lines into ' . $num_chunk . ' files');

$chunk_num = -1;
$file_lines = array();
$sum = 0;
foreach($data_in as $idx=>$data)
{
  if(0 == ($idx % $line_chunk))
  {
    if(-1 < $chunk_num)
      $sum += count($file_lines[$chunk_num]);
    $chunk_num++;
    $file_lines[$chunk_num] = array();
  }
  $file_lines[$chunk_num][] = $data;
}
$sum += count($file_lines[$chunk_num]);

util::out($sum);
util::out('processed ' . $chunk_num . ' chunks');

$fprefix = substr($infileName,0,-4);
$header = '"' .  util::flatten($header,'","') . '"' . PHP_EOL;
foreach($file_lines as $chunk=>$data)
{
  $fname = $fprefix . '_' . $chunk . '.csv';
  util::out('writing chunk ' . $chunk . ' to ' . $fname . ' (' . count($data) .  ' lines)');
  $file = fopen($fname,'w');
  fwrite($file,$header);
  foreach($data as $line) fwrite($file,$line);
  fclose($file);
}

?>
