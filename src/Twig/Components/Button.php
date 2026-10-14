<?php

namespace Macc\CommonBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Macc:Button', template: '@MaccCommon/components/Button.html.twig')]
class Button
{
    public string $type = 'button';
    public string $class = '';
    public ?string $icon = null;
    public ?string $href = null;
    public ?string $variant = null;
    public bool $border = true;
    public ?string $textVariant = null;
}
