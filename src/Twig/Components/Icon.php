<?php

namespace Macc\CommonBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Macc:Icon', template: '@MaccCommon/components/Icon.html.twig')]
class Icon
{
    public string $name;
}
