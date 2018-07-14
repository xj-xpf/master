<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
  <html>
  	<head>
  		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  			<title>NetBeans PHP debugging sample</title>
		</head>
<body>
    <form action="netbeans.php" method="POST">
       Enter the first integer, please:
       <input type="text" name="first_integer"/><br/>
       Enter the second integer, please:
       <input type="text" name="second_integer"/><br/>
       <input type="submit" name="enter" value="Enter"/>
</form>
	<?php
	/**
	 php.ini  xdebug 配置
	 
[xdebug]
#zend_extension = "d:/wamp/bin/php/php5.4.3/zend_ext/php_xdebug-2.2.0-5.4-vc9.dll"
#xdebug.profiler_enable = off
#xdebug.profiler_enable_trigger = off
#xdebug.profiler_output_name = cachegrind.out.%t.%p
#xdebug.profiler_output_dir = "d:/wamp/tmp"

#xdebug.remote_enable=0
#xdebug.remote_host=127.0.0.1
#xdebug.remote_port=9000
#xdebug.remote_handler=dbgp
	*/
	
	
        // 参考网址http://www.cnblogs.com/huangjacky/archive/2010/12/30/1921636.html
  	 if (array_key_exists ("first_integer", $_POST) &&
    array_key_exists ("second_integer", $_POST)) {
				$result = calculate_sum_of_factorials ($_POST["first_integer"], $_POST["second_integer"]);
                echo "Sum of factorials is " . $sum_of_factorials;
            }
  	
	    function calculate_sum_of_factorials ($argument1, $argument2) {
  	 	$factorial1 = calculate_factorial ($argument1);
  	 	$factorial2 = calculate_factorial ($argument2);
  	 	$result = calculate_sum ($factorial1, $factorial2);
  	 	return $result;
  		}
                
echo '11111';       
echo '22222';

echo '333333';

echo '44444444';
	
	  function calculate_factorial ($argument) {
  	  	$factorial_result = 1;
  	 	for ($i=1; $i<=$argument; $i++) {
  	 		$factorial_result = $factorial_result*$i;
  	 	}
  			return $factorial_result;
  		}
	  
	    function calculate_sum ($argument1, $argument2) {
 			return $argument1 + $argument2;
     	}	
?>
  </body>
</html>