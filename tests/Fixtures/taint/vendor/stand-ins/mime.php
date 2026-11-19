<?php

namespace Symfony\Component\Mime;

class Message
{
}

class Email extends Message
{
    public function subject(string $subject): static
    {
        return $this;
    }

    public function text($body, string $charset = 'utf-8'): static
    {
        return $this;
    }

    public function html($body, string $charset = 'utf-8'): static
    {
        return $this;
    }
}
