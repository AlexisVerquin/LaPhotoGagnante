<?php

namespace App\Entity\Trait;

interface DateInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $created_at): static;

    public function getUpdatedAt(): ?\DateTime;

    public function setUpdatedAt(\DateTime $updated_at): static;
}
