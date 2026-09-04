<?php

namespace Macc\CommonBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'Macc:Alert', template: '@MaccCommon/components/Alert.html.twig')]
class Alert
{
    public string $class = '';
    public string $type = 'primary';
}
