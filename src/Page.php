<?php

namespace Dmpty\PdOrm;

class Page extends Collection
{
    private int $total;

    private int $perPage;

    private int $current;

    private int $page;

    public function __construct(Collection|array $data, int $total, int $perPage, int $current)
    {
        $this->total = $total;
        $this->perPage = $perPage;
        $this->current = $current;
        $this->page = floor($total / $perPage);
        parent::__construct($data);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getCurrent(): int
    {
        return $this->current;
    }

    public function getPage(): int
    {
        return $this->page;
    }
}
