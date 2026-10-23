<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseDao.php';

final class EntityDao extends BaseDao {
    public function __construct(PDO $pdo, string $table) {
        $allowed=['users','roles','products','orders','reviews'];
        if(!in_array($table,$allowed,true)) throw new InvalidArgumentException('Nepoznata tabela.');
        parent::__construct($pdo,$table);
    }
    public function all(): array {
        if($this->table==='products') return $this->pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
        if($this->table==='reviews') return $this->pdo->query('SELECT r.*,u.name AS user_name,p.name AS product_name FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id ORDER BY r.id DESC')->fetchAll();
        if($this->table==='orders') return $this->pdo->query('SELECT o.*,u.name AS user_name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC')->fetchAll();
        if($this->table==='users') return $this->pdo->query('SELECT u.id,u.name,u.email,u.role_id,r.name AS role FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id DESC')->fetchAll();
        return parent::all();
    }
    public function userOrders(int $userId): array {
        $s=$this->pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC');$s->execute([$userId]);return $s->fetchAll();
    }
    public function createOrder(int $userId,array $items): array {
        if(!$items) throw new InvalidArgumentException('Korpa je prazna.');
        $this->pdo->beginTransaction();
        try {
            $total=0.0;$lines=[];
            foreach($items as $item) {
                $pid=(int)($item['product_id']??0);$qty=(int)($item['quantity']??0);
                if($pid<1||$qty<1||$qty>99) throw new InvalidArgumentException('Neispravan proizvod ili količina.');
                $s=$this->pdo->prepare('SELECT id,name,price,stock FROM products WHERE id=? FOR UPDATE');$s->execute([$pid]);$p=$s->fetch();
                if(!$p || (int)$p['stock']<$qty) throw new InvalidArgumentException('Proizvod nije dostupan u traženoj količini.');
                $total+=(float)$p['price']*$qty;$lines[]=['id'=>$pid,'quantity'=>$qty,'price'=>(float)$p['price']];
                $u=$this->pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?');$u->execute([$qty,$pid]);
            }
            $s=$this->pdo->prepare("INSERT INTO orders(user_id,total,status,items_json) VALUES(?,?,'pending',?)");$s->execute([$userId,$total,json_encode($lines)]);
            $id=(int)$this->pdo->lastInsertId();$this->pdo->commit();return $this->find($id)??[];
        } catch(Throwable $e) { $this->pdo->rollBack();throw $e; }
    }
}
