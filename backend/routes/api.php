<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/ApiService.php';
require_once __DIR__ . '/../middleware/Middleware.php';

$pdo=Flight::get('db');
$service=new ApiService($pdo);
$tables=['products','roles','users','orders','reviews'];

Flight::route('GET /api/health', function(){ Flight::json(['status'=>'ok','app'=>'Watchly API']); });
Flight::route('GET /api/exchange-rate', function() use($service){try{Flight::json(['data'=>$service->exchangeRates()]);}catch(Throwable $e){Flight::response()->status(503);Flight::json(['message'=>$e->getMessage()]);}});

Flight::route('POST /api/auth/register', function() use($pdo) {
    $b=watchly_body();$name=trim((string)($b['name']??''));$email=strtolower(trim((string)($b['email']??'')));$password=(string)($b['password']??'');
    if(strlen($name)<2||strlen($name)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8){watchly_json(400,['message'=>'Unesi ime, ispravan email i lozinku od najmanje 8 znakova.']);return;}
    $s=$pdo->prepare('SELECT id FROM users WHERE email=?');$s->execute([$email]);if($s->fetch()){watchly_json(409,['message'=>'Ovaj email je već registrovan.']);return;}
    $s=$pdo->prepare("INSERT INTO users(name,email,password_hash,role_id) VALUES(?,?,?,1)");$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    Flight::get('logger')->info('Successful registration user='.$pdo->lastInsertId().' email='.$email);Flight::response()->status(201);Flight::json(['message'=>'Račun je kreiran.']);
});
Flight::route('POST /api/auth/login', function() use($pdo) {
    $b=watchly_body();$email=strtolower(trim((string)($b['email']??'')));$password=(string)($b['password']??'');
    $s=$pdo->prepare('SELECT u.id,u.name,u.email,u.password_hash,u.role_id,r.name AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=?');$s->execute([$email]);$u=$s->fetch();
    if(!$u||!password_verify($password,$u['password_hash'])){Flight::get('logger')->error('Failed login email='.$email);watchly_json(401,['message'=>'Email ili lozinka nisu ispravni.']);return;}
    $config=Flight::get('config');$now=time();$payload=['iss'=>'watchly','iat'=>$now,'exp'=>$now+60*60*8,'sub'=>(string)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
    $token=Firebase\JWT\JWT::encode($payload,$config['jwt_secret'],'HS256');Flight::get('logger')->info('Successful login user='.$u['id'].' role='.$u['role']);
    Flight::json(['token'=>$token,'user'=>['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]]);
});
Flight::route('GET /api/auth/me', function(){ $u=watchly_require_auth();Flight::json(['data'=>$u]); });

foreach($tables as $table) {
    Flight::route('GET /api/'.$table, function() use($table,$service) {
        $user=watchly_user();
        if(in_array($table,['orders','users'],true) && !$user){watchly_require_auth();return;}
        if($table==='orders' && $user['role']!=='admin'){Flight::json(['data'=>$service->dao('orders')->userOrders($user['id'])]);return;}
        if($table==='users' && $user['role']!=='admin'){watchly_json(403,['message'=>'Samo administrator može pregledati korisnike.']);return;}
        $rows=$service->dao($table)->all();
        if($table==='users') foreach($rows as &$row) unset($row['password_hash']);
        Flight::json(['data'=>$rows]);
    });
    Flight::route('GET /api/'.$table.'/@id:[0-9]+', function($id) use($table,$service) {
        if(in_array($table,['orders','users'],true))$user=watchly_require_auth();else $user=watchly_user();
        if($table==='users' && $user['role']!=='admin'){watchly_json(403,['message'=>'Samo administrator može pregledati korisnike.']);return;}
        $row=$service->dao($table)->find((int)$id);if(!$row){watchly_json(404,['message'=>'Zapis nije pronađen.']);return;}
        if($table==='orders' && $user['role']!=='admin' && (int)$row['user_id']!==$user['id']){watchly_json(403,['message'=>'Nemaš pristup ovoj narudžbi.']);return;}
        unset($row['password_hash']);Flight::json(['data'=>$row]);
    });
    Flight::route('POST /api/'.$table, function() use($table,$service) {
        $user=watchly_require_admin();
        if($table==='orders') {watchly_json(400,['message'=>'Narudžbe kreiraju kupci kroz checkout.']);return;}
        $data=$service->cleanEntity($table,watchly_body());
        if($table==='users'){ $data['password_hash']=password_hash(bin2hex(random_bytes(8)),PASSWORD_DEFAULT); }
        if($table==='reviews'){ $data['user_id']=$user['id']; }
        $row=$service->dao($table)->create($data);Flight::response()->status(201);Flight::json(['data'=>$row]);
    });
    Flight::route('PUT /api/'.$table.'/@id:[0-9]+', function($id) use($table,$service) {
        $user=watchly_require_admin();$data=$service->cleanEntity($table,watchly_body(),false);
        if($table==='users'&&isset($data['role_id'])&&(int)$data['role_id']<1){watchly_json(400,['message'=>'Neispravna uloga.']);return;}
        $row=$service->dao($table)->update((int)$id,$data);if(!$row){watchly_json(404,['message'=>'Zapis nije pronađen.']);return;}unset($row['password_hash']);Flight::json(['data'=>$row]);
    });
    Flight::route('DELETE /api/'.$table.'/@id:[0-9]+', function($id) use($table,$service) {
        watchly_require_admin();if(!$service->dao($table)->delete((int)$id)){watchly_json(404,['message'=>'Zapis nije pronađen.']);return;}Flight::json(['message'=>'Zapis je obrisan.']);
    });
}
Flight::route('POST /api/orders', function() use($service) {
    $user=watchly_require_auth();$b=watchly_body();
    try{$row=$service->dao('orders')->createOrder($user['id'],$b['items']??[]);Flight::response()->status(201);Flight::json(['data'=>$row]);}
    catch(InvalidArgumentException $e){watchly_json(400,['message'=>$e->getMessage()]);}
});
Flight::route('POST /api/reviews', function() use($service) {
    $user=watchly_require_auth();$b=watchly_body();
    try{$data=$service->cleanEntity('reviews',$b);$data['user_id']=$user['id'];$row=$service->dao('reviews')->create($data);Flight::response()->status(201);Flight::json(['data'=>$row]);}
    catch(InvalidArgumentException $e){watchly_json(400,['message'=>$e->getMessage()]);}
});

Flight::route('GET /api/docs', function(){header('Location: /api/swagger');exit;});
Flight::route('GET /api/swagger', function(){header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html><head><title>Watchly API docs</title><link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head><body><div id="swagger-ui"></div><script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script><script>SwaggerUIBundle({url:"/api/openapi.json",dom_id:"#swagger-ui"});</script></body></html>';});
Flight::route('GET /api/openapi.json', function(){Flight::json(['openapi'=>'3.0.3','info'=>['title'=>'Watchly API','version'=>'1.0.0'],'servers'=>[['url'=>'/api']],'paths'=>['/health'=>['get'=>['summary'=>'Provjera API-ja','responses'=>['200'=>['description'=>'OK']]]],'/products'=>['get'=>['summary'=>'Lista proizvoda','responses'=>['200'=>['description'=>'OK']]],'post'=>['summary'=>'Kreiranje proizvoda (admin)','responses'=>['201'=>['description'=>'Kreirano'],'401'=>['description'=>'Neprijavljen'],'403'=>['description'=>'Zabranjeno']]]],'/auth/login'=>['post'=>['summary'=>'Prijava i JWT token','responses'=>['200'=>['description'=>'Uspješna prijava'],'401'=>['description'=>'Neispravni podaci']]]],'/auth/register'=>['post'=>['summary'=>'Registracija','responses'=>['201'=>['description'=>'Kreirano']]]],'/exchange-rate'=>['get'=>['summary'=>'Kurs EUR/BAM preko vanjskog API-ja','responses'=>['200'=>['description'=>'Kurs'],'503'=>['description'=>'API nedostupan']]]]],'components'=>['securitySchemes'=>['bearerAuth'=>['type'=>'http','scheme'=>'bearer','bearerFormat'=>'JWT']]]]);});
