<?php


function get_nodes(){
	
	if(file_exists('nodes.txt')): 
	
		$nodes_file = file_get_contents('nodes.txt'); 
		$nodes 		= explode("\n", $nodes_file);
		
		$return['total_nodes'] 		= 0;
		$return['total_proposals'] 	= 0;
		$return['max_relay'] 		= 0;
		
		$return['stats']						= []; 
		$return['stats']['ERROR']				= 0; 
		$return['stats']['WAIT_FOR_SYNCING']	= 0;
		$return['stats']['SYNC_STARTED']		= 0;
		$return['stats']['SYNC_FINISHED']		= 0;
		$return['stats']['PERSIST_FINISHED']	= 0;
		$return['stats']['OFFLINE']				= 0;
		
		
		foreach($nodes as $node) :
			
			// Whitelines .. 
			if(isset($node) AND !empty($node)) : 
				
				$data 	= explode(',', $node); 
				$ip 	= $data[0]; 
				$name 	= $data[1];
				
				$node 	= get_node_status($ip);
				
				if(!empty($node)) : 
					
					// Common 
					
					$return['nodes'][$ip]['ip'] 	= $ip;
					$return['nodes'][$ip]['name'] 	= $name;
					
					// Error 
						
					if(isset($node['error'])):
					
						$return['nodes'][$ip]['style']['border'] 	= 'border-alert';
						$return['nodes'][$ip]['style']['cell'] 		= 'bg-alert';
						$return['nodes'][$ip]['style']['img'] 		= 'core/img/warning.svg';
						
						$return['nodes']['syncState'] = $node['error']['message']; 
						
						$return['nodes'][$ip]['height']				= 0;
						$return['nodes'][$ip]['relayMessageCount'] 	= 0; 
						$return['nodes'][$ip]['uptime'] 			= 0;
						
						$return['stats']['ERROR']++;
					
					else : 
						
						$return['nodes'][$ip]['syncState'] 			= str_replace('_', ' ', $node['result']['syncState']);
						$return['nodes'][$ip]['height'] 			= $node['result']['height'];
						$return['nodes'][$ip]['relayMessageCount'] 	= $node['result']['relayMessageCount'];
						$return['nodes'][$ip]['version'] 			= $node['result']['version'];
						$return['nodes'][$ip]['proposalSubmitted'] 	= $node['result']['proposalSubmitted'];
						$return['nodes'][$ip]['uptime'] 			= secondsToTime($node['result']['uptime']);
						$return['nodes'][$ip]['id']					= $node['result']['id']; 
						
						
						if($node['result']['proposalSubmitted'] != 0):
							$return['total_proposals']++;
						endif;
						
						$return['stats'][$node['result']['syncState']]++; 
						
						switch($node['result']['syncState']) : 
						
							case 'WAIT_FOR_SYNCING': 
								$return['nodes'][$ip]['style']['border'] 	= 'border-warning';
								$return['nodes'][$ip]['style']['cell'] 		= 'bg-warning';
								$return['nodes'][$ip]['style']['img'] 		= 'core/img/sync.svg';
							break; 
							
							case 'SYNC_STARTED': 
								$return['nodes'][$ip]['style']['border'] 	= 'border-start';
								$return['nodes'][$ip]['style']['cell'] 		= 'bg-start';
								$return['nodes'][$ip]['style']['img'] 		= 'core/img/start.svg';
							break; 
							
							case 'SYNC_FINISHED': 
								$return['nodes'][$ip]['style']['border'] 	= 'border-success';
								$return['nodes'][$ip]['style']['cell'] 		= 'bg-success';
								$return['nodes'][$ip]['style']['img'] 		= 'core/img/finish.svg';
							break; 
							
							case 'PERSIST_FINISHED': 
								$return['nodes'][$ip]['style']['border'] 	= 'border-success';
								$return['nodes'][$ip]['style']['cell'] 		= 'bg-success';
								$return['nodes'][$ip]['style']['img'] 		= 'core/img/mining.svg';
							break; 
						
						endswitch; 
						
						// Relay calculation
						
						$node_uptime = secondsToHours($node['result']['uptime']);
							
						if($node_uptime > 0) : 
							$return['nodes'][$ip]['relayperhour'] = perso_round(($node['result']['relayMessageCount']/$node_uptime), 0 ); 
							$true_relay = $node['result']['relayMessageCount']/$node_uptime; 
						else : 
							$return['nodes'][$ip]['relayperhour'] = perso_round($node['result']['relayMessageCount'], 0 );
							$true_relay = $node['result']['relayMessageCount']; 
						endif;
						
						if($true_relay > $return['max_relay']):
							$return['max_relay'] = $true_relay; 
						endif;
						
						// Sync state calculation 
						
						if($node['result']['syncState'] == 'SYNC_STARTED'):
							$return['nodes'][$ip]['remain'] = perso_round(($node['result']['height']/$block['blockCount'])*100, 4);
						endif; 
						
					endif;
				
				else : 
					
					$return['nodes'][$ip]['style']['border'] 	= 'border-alert';
					$return['nodes'][$ip]['style']['cell'] 		= 'bg-alert';
					$return['nodes'][$ip]['style']['img'] 		= 'core/img/warning.svg';
					
					$return['nodes'][$ip]['syncState'] 				= 'OFFLINE';
					$return['nodes'][$ip]['height']				= 0;
					$return['nodes'][$ip]['relayMessageCount'] 	= 0; 
					$return['nodes'][$ip]['uptime'] 			= 0;
					
					$return['stats']['OFFLINE']++; 
					
				endif; 
				
				
				$return['total_nodes']++; 
				
			endif; 
		
		endforeach; 
		
		return $return; 
	
	else: 
	
		return false; 
	
	endif;
	
	
}


function get_json($url){
	
	$headers = [
		"Accept: application/vnd.github.v3+json",
		"user-agent: nWatch"
	]; 
	
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	
	$server_output = curl_exec($ch);
		
	curl_close ($ch);
	
	if(!empty($server_output)) : 
		
		$json = json_decode($server_output, true);
		return $json;
		
	else : 
		
		return false; 
	
	endif; 
	
}

function get_node_status($ip){
	
	$url = 'http://'.$ip.':30003/';
	
	$ch = curl_init($url);
		
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS,'{"jsonrpc":"2.0","method":"getnodestate","params":{},"id":1}');
	
	$server_output = curl_exec($ch);
	
	curl_close ($ch);
	
	$json = json_decode($server_output, true); 
		
	return $json; 
	
}

function secondsToHours($seconds){
	
	$minutes 	= $seconds/60; 
	$hours 		= round($minutes/60);
	
	return $hours;  
	
}

function secondsToTime($seconds) {

	$dtF = new \DateTime('@0');
	$dtT = new \DateTime("@$seconds");

	return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');

}

function perso_round($value,$float, $separator = ''){
	
	$return = number_format($value, $float, ',', $separator); 
	
	return $return; 
	
}

function time_elapsed_string($datetime, $full = false) {
	
	$now 	= new DateTime;
	$ago 	= new DateTime($datetime);
	$diff 	= $now->diff($ago);

	$diff->w = floor($diff->d / 7);
	$diff->d -= $diff->w * 7;

	$string = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'hour',
		'i' => 'minute',
		's' => 'second',
	);
	
	foreach ($string as $k => &$v) {
		if ($diff->$k) {
			$v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
		} else {
			unset($string[$k]);
		}
	}

	if (!$full) $string = array_slice($string, 0, 1);
	
	return $string ? implode(', ', $string) . ' ago' : 'just now';
	
}

?>