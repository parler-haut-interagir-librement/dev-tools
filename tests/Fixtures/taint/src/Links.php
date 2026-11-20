<?php

declare(strict_types=1);

namespace App;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Each method puts something in an email: the lines the taint analysis reports end with "reported".
 */
final class Links
{
    public function __construct(private UrlGeneratorInterface $urls, private Environment $twig, private string $origin)
    {
    }

    public function fromTheHost(Request $request, Email $email): void
    {
        $email->text('https://'.$request->getHost().'/reset'); // reported
    }

    public function fromTheSchemeAndHost(Request $request, Email $email): void
    {
        $email->html('<a href="'.$request->getSchemeAndHttpHost().'/reset">reset</a>'); // reported
    }

    public function fromTheHostHeader(Request $request, Email $email): void
    {
        $email->text('https://'.$request->headers->get('Host').'/reset'); // reported
    }

    public function fromTheServerVariable(Request $request, Email $email): void
    {
        $email->text('https://'.(string) $request->server->get('HTTP_HOST').'/reset'); // reported
    }

    public function fromAnAbsoluteUrl(TemplatedEmail $email): void
    {
        $email->context(['link' => $this->urls->generate('reset', ['token' => 't'], UrlGeneratorInterface::ABSOLUTE_URL)]); // reported
    }

    public function throughATemplate(Request $request, Email $email): void
    {
        $email->text($this->twig->render('email.txt.twig', ['link' => $request->getUri()])); // reported
    }

    public function fromTheConfiguredOrigin(Email $email): void
    {
        $email->text($this->origin.$this->urls->generate('reset', ['token' => 't']));
    }

    public function fromAnotherHeader(Request $request, Email $email): void
    {
        $email->text('Signed in from '.(string) $request->headers->get('User-Agent'));
    }

    public function fromThePath(Request $request, Email $email): void
    {
        $email->text($this->origin.$request->getPathInfo());
    }

    public function fromARelativePath(Email $email): void
    {
        $email->text($this->origin.$this->urls->generate('reset', [], UrlGeneratorInterface::RELATIVE_PATH));
    }

    public function fromAnotherServerVariable(Request $request, Email $email): void
    {
        $email->text('Agent: '.(string) $request->server->get('HTTP_USER_AGENT'));
    }

    public function anyHeader(Request $request, Email $email, string $name): void
    {
        $email->text('Seen: '.(string) $request->headers->get($name)); // reported
    }

    public function anyReference(Email $email, int $type): void
    {
        $email->text($this->urls->generate('reset', [], $type)); // reported
    }

    public function namedReference(Email $email): void
    {
        $email->text($this->urls->generate('reset', referenceType: UrlGeneratorInterface::NETWORK_PATH)); // reported
    }

    public function fromTheHostToTheSubject(Request $request, Email $email): void
    {
        $email->subject('Your account at '.$request->getHost());
    }
}
