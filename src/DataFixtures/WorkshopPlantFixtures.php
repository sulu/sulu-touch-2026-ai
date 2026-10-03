<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Application\MessageHandler\CreatePageMessageHandler;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\CreateAttributeGroupMessage;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fifteen houseplants, five per light level. Runs on its own: `doctrine:fixtures:load --append`.
 */
final class WorkshopPlantFixtures extends Fixture
{
    use HandleTrait;

    private const LOCALE = 'en';
    private const WEBSPACE = 'website';
    private const PAGE_PRODUCTS = '11111111-0000-4000-8000-000000000003';

    private const LIGHT = ['shade' => 'Shade', 'bright-indirect' => 'Bright, indirect', 'full-sun' => 'Full sun'];
    private const WATERING = ['rarely' => 'Rarely', 'weekly' => 'Weekly', 'often' => 'Often'];
    private const CARE = ['easy' => 'Easy', 'medium' => 'Medium', 'demanding' => 'Demanding'];
    private const PETS = ['yes' => 'Pet safe', 'no' => 'Toxic to pets'];

    /**
     * name, claim, light, watering, care, pets, height in cm
     */
    private const PLANTS = [
        ['Snake Plant', 'Survives almost anything.', 'shade', 'rarely', 'easy', 'no', 80],
        ['ZZ Plant', 'Glossy and nearly unkillable.', 'shade', 'rarely', 'easy', 'no', 70],
        ['Cast Iron Plant', 'Lives up to its name.', 'shade', 'weekly', 'easy', 'yes', 60],
        ['Peace Lily', 'Droops loudly when thirsty.', 'shade', 'weekly', 'medium', 'no', 50],
        ['Parlor Palm', 'Calm and pet safe.', 'shade', 'weekly', 'easy', 'yes', 90],
        ['Monstera', 'The famous split leaves.', 'bright-indirect', 'weekly', 'easy', 'no', 150],
        ['Fiddle Leaf Fig', 'Beautiful and moody.', 'bright-indirect', 'weekly', 'demanding', 'no', 180],
        ['Spider Plant', 'Makes babies, safe for cats.', 'bright-indirect', 'weekly', 'easy', 'yes', 40],
        ['Calathea', 'Leaves that move with the day.', 'bright-indirect', 'often', 'demanding', 'yes', 60],
        ['Boston Fern', 'Loves humidity and pets.', 'bright-indirect', 'often', 'medium', 'yes', 50],
        ['Aloe Vera', 'Sun lover with a first-aid kit inside.', 'full-sun', 'rarely', 'easy', 'no', 40],
        ['Jade Plant', 'A tiny tree for sunny sills.', 'full-sun', 'rarely', 'easy', 'no', 60],
        ['Echeveria', 'A rosette of stone.', 'full-sun', 'rarely', 'medium', 'yes', 15],
        ['Bird of Paradise', 'Big, bold and thirsty.', 'full-sun', 'often', 'demanding', 'no', 200],
        ['Ponytail Palm', 'Funny hair, safe for cats.', 'full-sun', 'rarely', 'easy', 'yes', 100],
    ];

    public function __construct(MessageBusInterface $messageBus, private readonly PageRepositoryInterface $pageRepository)
    {
        $this->messageBus = $messageBus;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createProductsPage();

        $group = $this->dispatchMessage(new CreateAttributeGroupMessage(['locale' => self::LOCALE, 'name' => 'Plant facts']));
        \assert($group instanceof AttributeGroupInterface);
        $groupUuid = (string) $group->getUuid();

        $light = $this->options('light', 'Light', self::LIGHT, $groupUuid);
        $watering = $this->options('watering', 'Watering', self::WATERING, $groupUuid);
        $care = $this->options('care', 'Care effort', self::CARE, $groupUuid);
        $pets = $this->options('pets', 'Pets', self::PETS, $groupUuid);
        $height = $this->dispatchMessage(new CreateAttributeMessage([
            'locale' => self::LOCALE,
            'key' => 'height',
            'type' => 'number',
            'name' => 'Height',
            'group' => $groupUuid,
            'config' => ['unit' => 'CENTIMETER', 'displayFormat' => '%value% %unit%'],
            'filterable' => true,
        ]));
        \assert($height instanceof AttributeInterface);

        $family = $this->dispatchMessage(new CreateProductFamilyMessage([
            'locale' => self::LOCALE,
            'key' => 'houseplants',
            'name' => 'Houseplants',
            'attributes' => \array_map(
                static fn (AttributeInterface $attribute): array => ['id' => (string) $attribute->getUuid(), 'required' => false, 'variantSpecific' => false],
                [$light, $watering, $care, $pets, $height],
            ),
        ]));
        \assert($family instanceof ProductFamilyInterface);

        foreach (self::PLANTS as $index => [$name, $claim, $lightKey, $wateringKey, $careKey, $petKey, $heightCm]) {
            $uuid = \sprintf('22222222-0000-4000-8000-%012d', $index + 1);

            $this->dispatchMessage(new CreateProductMessage([
                'uuid' => $uuid,
                'locale' => self::LOCALE,
                'template' => 'product',
                'productFamily' => (string) $family->getUuid(),
                'code' => \sprintf('PL-%d', 1001 + $index),
                'status' => 'available',
                'attributes' => [
                    (string) $light->getUuid() => $lightKey,
                    (string) $watering->getUuid() => $wateringKey,
                    (string) $care->getUuid() => $careKey,
                    (string) $pets->getUuid() => $petKey,
                    (string) $height->getUuid() => $heightCm,
                ],
                'title' => $name,
                'headline' => $name,
                'claim' => $claim,
                'description' => '<p>' . $claim . '</p>',
                'url' => ['page' => ['uuid' => self::PAGE_PRODUCTS, 'path' => '/products'], 'suffix' => \strtolower(\str_replace(' ', '-', $name))],
            ]));

            $this->dispatchMessage(new ApplyWorkflowTransitionProductMessage(['uuid' => $uuid], self::LOCALE, 'publish'));
        }
    }

    private function createProductsPage(): void
    {
        $homepage = $this->pageRepository->findOneBy(['parentId' => null, 'locale' => self::LOCALE, 'stage' => 'draft']);
        $parent = $homepage instanceof PageInterface ? $homepage->getUuid() : CreatePageMessageHandler::HOMEPAGE_PARENT_ID;

        $this->dispatchMessage(new CreatePageMessage(self::WEBSPACE, $parent, [
            'uuid' => self::PAGE_PRODUCTS,
            'locale' => self::LOCALE,
            'template' => 'products',
            'title' => 'Products',
            'url' => '/products',
            'article' => '<p>Fifteen houseplants.</p>',
            'products' => [],
            'navigationContexts' => ['main'],
        ]));
        $this->dispatchMessage(new ApplyWorkflowTransitionPageMessage(['uuid' => self::PAGE_PRODUCTS], self::LOCALE, 'publish'));
    }

    /**
     * @param array<string, string> $options
     */
    private function options(string $key, string $name, array $options, string $group): AttributeInterface
    {
        $list = [];
        foreach ($options as $optionKey => $label) {
            $list[] = ['key' => $optionKey, 'name' => $label];
        }

        $attribute = $this->dispatchMessage(new CreateAttributeMessage([
            'locale' => self::LOCALE,
            'key' => $key,
            'type' => 'options',
            'name' => $name,
            'group' => $group,
            'options' => $list,
            'filterable' => true,
        ]));
        \assert($attribute instanceof AttributeInterface);

        return $attribute;
    }

    private function dispatchMessage(object $message): mixed
    {
        return $this->handle(new Envelope($message, [new EnableFlushStamp()]));
    }
}
