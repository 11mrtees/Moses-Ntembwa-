<?php
// chat.php - Ask MR TEES: retrieves the best knowledge, then asks Claude to answer in the MR TEES style
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/common.php';
$cfg  = require __DIR__.'/config.php';
$kb   = load_json(__DIR__.'/knowledge.json',['settings'=>[],'entries'=>[]]);
$in   = json_decode(file_get_contents('php://input'),true) ?: [];
$msg  = mb_substr(trim($in['message'] ?? ''),0,500);
$period = in_array($in['period']??'',['morning','afternoon','evening']) ? $in['period'] : 'day';
$hist = is_array($in['history']??null) ? array_slice($in['history'],-6) : [];
$greet = ['morning'=>'Good morning','afternoon'=>'Good afternoon','evening'=>'Good evening'][$period] ?? 'Hello';
function out($r,$wa=false){ echo json_encode(['reply'=>$r,'whatsapp'=>$wa]); exit; }
if($msg==='') out("$greet! Type your question and I will help.");
$t=norm($msg); $tokens=$t===''?[]:explode(' ',$t);
$set=$kb['settings']??[];

// 1. Serious situations: gentle reply, no AI jokes
foreach(['died','death','funeral','passed away','grieving','suicide','hungry','abused','desperate','depressed','homeless'] as $w)
  if(strpos($t,$w)!==false) out($set['distress'] ?? 'I am sorry you are going through this. Please talk to Moses on WhatsApp.',true);

// 2. Needs a real person
foreach(['lend me','borrow','loan me','give me money','send me money','assist me with','help me apply','apply for me','do my application','fill my application','refund','complain','pay for me'] as $w)
  if(strpos($t,$w)!==false)
    out("I understand you. That one needs a real person, not me. Please contact Moses on WhatsApp (0771043702) and explain your case, so he can look at it personally. I cannot lend money or handle applications by myself, but I can explain the steps and what you need.",true);

// 3. Retrieve best knowledge
$scored=[];
foreach($kb['entries'] as $e){ $scored[]=[score_entry($e,$t,$tokens),$e]; }
usort($scored,fn($a,$b)=>$b[0]<=>$a[0]);
$use=[]; foreach(array_slice($scored,0,6) as [$s,$e]) if($s>=2) $use[$e['id']]=$e;
foreach($kb['entries'] as $e) if(in_array($e['id'],['registration_fee','service_price','services','password_safety'])) $use[$e['id']]=$e;
$topScore=$scored[0][0] ?? 0;

// 4. Log questions the knowledge could not answer (training queue)
if($topScore<2){
  $path=__DIR__.'/unanswered.json'; $u=load_json($path,[]);
  $key=mb_substr($msg,0,200); $u[$key]=['count'=>($u[$key]['count']??0)+1,'last'=>date('Y-m-d H:i')];
  if(count($u)>300) $u=array_slice($u,-300,null,true);
  save_json($path,$u);
}

$persona=@file_get_contents(__DIR__.'/persona.txt') ?: 'You are Ask MR TEES, a helpful education assistant in Zambia.';
$facts=implode("\n- ",array_map(fn($e)=>str_replace('{greeting}',$greet,$e['answer']),array_values($use)));
$system=$persona."\nTime of day: $period.\nMR TEES knowledge to use (trust this over your own memory; if it does not cover the question, answer carefully and say if unsure):\n- ".$facts;

$messages=[];
foreach($hist as $h) if(in_array($h['role']??'',['user','assistant']) && is_string($h['content']??null)) $messages[]=['role'=>$h['role'],'content'=>$h['content']];
$messages[]=['role'=>'user','content'=>$msg];

$ch=curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,
  CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.$cfg['api_key'],'anthropic-version: 2023-06-01'],
  CURLOPT_POSTFIELDS=>json_encode(['model'=>$cfg['model'],'max_tokens'=>450,'system'=>$system,'messages'=>$messages])]);
$res=json_decode(curl_exec($ch),true); curl_close($ch);
$text=$res['content'][0]['text'] ?? '';
if($text!=='') out(trim($text),(bool)preg_match('/whatsapp|0771043702/i',$text));

// 5. Backup if the AI cannot be reached
if($topScore>=2) out(str_replace('{greeting}',$greet,$scored[0][1]['answer']));
out($set['fallback'] ?? 'To be honest, I do not want to give you wrong information. Please ask Moses on WhatsApp.',true);
