<?php
$arr=file('img.txt');
$n=count($arr)-1;
for ($i=1;$i<=1;$i++){
$x=rand(0,$n);
header("Location:".$arr[$x],"\n");
}
?>