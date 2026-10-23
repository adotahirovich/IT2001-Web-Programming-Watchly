<?php
declare(strict_types=1);

class BaseDao {
    public function __construct(protected PDO $pdo, protected string $table) {}
    public function all(): array { return $this->pdo->query("SELECT * FROM `{$this->table}` ORDER BY id DESC")->fetchAll(); }
    public function find(int $id): ?array { $s=$this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE id=?");$s->execute([$id]);$r=$s->fetch();return $r?:null; }
    public function create(array $data): array {
        $columns=array_keys($data); if(!$columns) throw new InvalidArgumentException('Nema podataka za unos.');
        $quoted=implode(',',array_map(fn($x)=>"`$x`",$columns));$marks=implode(',',array_fill(0,count($columns),'?'));
        $s=$this->pdo->prepare("INSERT INTO `{$this->table}` ($quoted) VALUES ($marks)");$s->execute(array_values($data));
        return $this->find((int)$this->pdo->lastInsertId()) ?? [];
    }
    public function update(int $id,array $data): ?array {
        $data=array_filter($data,fn($v)=>true);if(!$data)return $this->find($id);
        $sets=implode(',',array_map(fn($x)=>"`$x` = ?",array_keys($data)));$s=$this->pdo->prepare("UPDATE `{$this->table}` SET $sets WHERE id=?");$s->execute([...array_values($data),$id]);return $this->find($id);
    }
    public function delete(int $id): bool { $s=$this->pdo->prepare("DELETE FROM `{$this->table}` WHERE id=?");$s->execute([$id]);return $s->rowCount()>0; }
}
