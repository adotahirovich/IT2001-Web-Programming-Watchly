<?php
declare(strict_types=1);
require_once __DIR__ . '/../dao/EntityDao.php';

final class ApiService {
    public function __construct(private PDO $pdo) {}
    public function dao(string $table): EntityDao { return new EntityDao($this->pdo,$table); }
    public function cleanEntity(string $table,array $input,bool $creating=true): array {
        $allowed=[
          'products'=>['name','category','description','price','stock','image_url'],
          'roles'=>['name','description'],
          'users'=>['name','email','role_id'],
          'orders'=>['status'],
          'reviews'=>['product_id','rating','comment']
        ];
        $data=array_intersect_key($input,array_flip($allowed[$table]??[]));
        if($table==='products') {
            if($creating && trim((string)($data['name']??''))==='') throw new InvalidArgumentException('Naziv proizvoda je obavezan.');
            if(isset($data['category'])&&!in_array($data['category'],['Satovi','Nakit'],true)) throw new InvalidArgumentException('Kategorija mora biti Satovi ili Nakit.');
            if(isset($data['price'])&&(!is_numeric($data['price'])||(float)$data['price']<0)) throw new InvalidArgumentException('Cijena mora biti pozitivan broj.');
            if(isset($data['stock'])&&(filter_var($data['stock'],FILTER_VALIDATE_INT)===false||(int)$data['stock']<0)) throw new InvalidArgumentException('Zaliha mora biti cijeli nenegativan broj.');
            if(isset($data['price']))$data['price']=(float)$data['price'];if(isset($data['stock']))$data['stock']=(int)$data['stock'];
        }
        if($table==='users') { if(isset($data['email'])&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Email nije ispravan.');if(isset($data['role_id']))$data['role_id']=(int)$data['role_id']; }
        if($table==='reviews'){if(isset($data['rating'])&&((int)$data['rating']<1||(int)$data['rating']>5))throw new InvalidArgumentException('Ocjena mora biti od 1 do 5.');if(isset($data['product_id']))$data['product_id']=(int)$data['product_id'];if(isset($data['rating']))$data['rating']=(int)$data['rating'];}
        if($table==='orders'&&isset($data['status'])&&!in_array($data['status'],['pending','paid','shipped','cancelled'],true))throw new InvalidArgumentException('Neispravan status narudžbe.');
        if(!$data && !in_array($table,['orders','reviews'],true))throw new InvalidArgumentException('Nema validnih podataka.');
        return $data;
    }
    public function exchangeRates(): array {
        $config=Flight::get('config');$url=rtrim($config['external_api_base'],'/').'/latest?base=EUR&symbols=BAM';
        $ctx=stream_context_create(['http'=>['timeout'=>(int)($config['external_api_timeout']??5),'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
        $result=@file_get_contents($url,false,$ctx);
        Flight::get('logger')->info('External API GET '.$url.' success='.(is_string($result)?'yes':'no'));
        if($result===false){Flight::get('logger')->error('External API failed: Frankfurter unavailable');throw new RuntimeException('Kursna lista trenutno nije dostupna.');}
        $data=json_decode($result,true);
        if(!is_array($data)||!isset($data['rates']['BAM'])){Flight::get('logger')->error('External API invalid response');throw new RuntimeException('Kursna lista trenutno nije dostupna.');}
        return ['base'=>'EUR','quote'=>'BAM','rate'=>(float)$data['rates']['BAM'],'date'=>$data['date']??null,'message'=>'Prikaz kursa EUR prema BAM za informativni prikaz.'];
    }
}
