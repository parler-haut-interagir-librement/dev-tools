<?php

namespace Twig;

class Environment
{
    public function render($name, array $context = []): string
    {
        return '';
    }
}

namespace Symfony\Bridge\Twig\Mime;

class TemplatedEmail extends \Symfony\Component\Mime\Email
{
    public function context(array $context): static
    {
        return $this;
    }
}
