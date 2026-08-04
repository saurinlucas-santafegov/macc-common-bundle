<?php

namespace Macc\CommonBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[\Symfony\Component\Console\Attribute\AsCommand(
    name: 'make:workflow',
    description: 'Crea toda la estructura base para un nuevo workflow (Enums, Entity, History, Repositories y Workflow).'
)]
class MakeWorkflowCommand extends Command
{
    private Filesystem $filesystem;
    private string $srcDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    )
    {
        parent::__construct();
        $this->srcDir = $projectDir . '/src';
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Nombre del workflow (ej: {$name})');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = ucfirst($input->getArgument('name') ?? $io->ask('Nombre del workflow (ej: {$name})'));

        $fs = $this->filesystem;

        // Directorios base
        $baseDir = $this->srcDir;
        $enumDir = "$baseDir/Enum";
        $entityDir = "$baseDir/Entity/Workflow";
        $repositoryDir = "$baseDir/Repository";
        $workflowDir = "$baseDir/Workflow";
        $securityDir = "$baseDir/Security/Voter";

        $fs->mkdir([$enumDir, $entityDir, $repositoryDir, $workflowDir]);

        // Rutas de archivos
        $statesFile = "$enumDir/{$name}States.php";
        $actionsFile = "$enumDir/{$name}Actions.php";
        $entityFile = "$entityDir/{$name}.php";
        $entityHistoryFile = "$entityDir/{$name}History.php";
        $entityRepositoryFile = "$repositoryDir/{$name}Repository.php";
        $entityHistoryRepositoryFile = "$repositoryDir/{$name}HistoryRepository.php";
        $workflowFile = "$workflowDir/{$name}Workflow.php";
        $securityFile = "$securityDir/App/{$name}Voter.php";

        // ---------------- ENUMS ----------------
        $statesContent = <<<PHP
        <?php

        namespace App\\Enum;

        use Macc\\CommonBundle\\Workflow\\WorkflowStateInterface;

        enum {$name}States: string implements WorkflowStateInterface
        {
            case INIT = 'INIT';
            case IN_PROGRESS = 'IN_PROGRESS';
            case FINISHED = 'FINISHED';

            public function label(): string
            {
                return \$this->name;
            }

            public static function getStartingState(?\\Macc\\CommonBundle\\Workflow\\WorkflowActionInterface \$action = null): self|array
            {
                return self::INIT;
            }

            public static function getEndStates(): self|array
            {
                return [self::FINISHED];
            }
        }
        PHP;

        $actionsContent = <<<PHP
        <?php

        namespace App\\Enum;

        use Macc\\CommonBundle\\Workflow\\WorkflowActionInterface;
        use Macc\\CommonBundle\\Workflow\\WorkflowActionTrait;

        enum {$name}Actions: string implements WorkflowActionInterface
        {
            use WorkflowActionTrait;

            case START = 'START';
            case CANCEL = 'CANCEL';
            case FINISH = 'FINISH';

            public function label(): string
            {
                return \$this->name;
            }
        }
        PHP;

        // ---------------- ENTITY ----------------
        $entityContent = <<<PHP
        <?php

        namespace App\\Entity\\Workflow;

        use Macc\\CommonBundle\\Workflow\\AbstractWorkflowObject;
        use Macc\\CommonBundle\\Workflow\\AbstractWorkflowObjectHistory;
        use App\\Enum\\{$name}States;
        use App\\Enum\\{$name}Actions;
        use App\\Repository\\{$name}Repository;
        use Doctrine\\ORM\\Mapping as ORM;
        use Doctrine\\Common\\Collections\\ArrayCollection;
        use Doctrine\\Common\\Collections\\Collection;

        #[ORM\\Entity(repositoryClass: {$name}Repository::class)]
        class {$name} extends AbstractWorkflowObject
        {

            #[ORM\\OneToMany(mappedBy: 'workflowObject', targetEntity: {$name}History::class, cascade: ['persist', 'remove'])]
            private Collection \$histories;

            public function __construct()
            {
                \$this->histories = new ArrayCollection();
            }

            public function getStateClass(): string
            {
                return {$name}States::class;
            }

            public function getActionsClass(): string
            {
                return {$name}Actions::class;
            }

            public function getHistoriesClass(): string
            {
                return {$name}History::class;
            }

            public function getState(): ?{$name}States
            {
                return empty(\$this->getHistories()) ? null : \$this->getHistories()->last()->getState();
            }

            public function getHistories(): Collection
            {
                return \$this->histories;
            }

            public function addHistory(AbstractWorkflowObjectHistory \$history): static
            {
                if (!\$this->histories->contains(\$history)) {
                    \$this->histories->add(\$history);
                    \$history->setWorkflowObject(\$this);
                }
                return \$this;
            }

            public function removeHistory(AbstractWorkflowObjectHistory \$history): static
            {
                if (\$this->histories->removeElement(\$history)) {
                    if (\$history->getWorkflowObject() === \$this) {
                        \$history->setWorkflowObject(null);
                    }
                }
                return \$this;
            }
        }
        PHP;

        // ---------------- ENTITY HISTORY ----------------
        $entityHistoryContent = <<<PHP
        <?php

        namespace App\\Entity\\Workflow;

        use Macc\\CommonBundle\\Workflow\\AbstractWorkflowObject;
        use Macc\\CommonBundle\\Workflow\\AbstractWorkflowObjectHistory;
        use App\\Repository\\{$name}HistoryRepository;
        use Doctrine\\ORM\\Mapping as ORM;

        #[ORM\\Entity(repositoryClass: {$name}HistoryRepository::class)]
        class {$name}History extends AbstractWorkflowObjectHistory
        {
            #[ORM\\ManyToOne(targetEntity: {$name}::class, inversedBy: 'histories')]
            #[ORM\\JoinColumn(nullable: true, onDelete: 'CASCADE')]
            protected ?AbstractWorkflowObject \$workflowObject = null;

            public function getWorkflowObject(): ?AbstractWorkflowObject
            {
                return \$this->workflowObject;
            }

            public function setWorkflowObject(?AbstractWorkflowObject \$object): static
            {
                \$this->workflowObject = \$object;
                return \$this;
            }
        }
        PHP;

        // ---------------- REPOSITORIES ----------------
        $repositoryContent = <<<PHP
        <?php

        namespace App\\Repository;

        use App\\Entity\\Workflow\\{$name};
        use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;
        use Doctrine\\Persistence\\ManagerRegistry;

        class {$name}Repository extends ServiceEntityRepository
        {
            public function __construct(ManagerRegistry \$registry)
            {
                parent::__construct(\$registry, {$name}::class);
            }
        }
        PHP;

        $historyRepositoryContent = <<<PHP
        <?php

        namespace App\\Repository;

        use App\\Entity\\Workflow\\{$name}History;
        use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;
        use Doctrine\\Persistence\\ManagerRegistry;

        class {$name}HistoryRepository extends ServiceEntityRepository
        {
            public function __construct(ManagerRegistry \$registry)
            {
                parent::__construct(\$registry, {$name}History::class);
            }
        }
        PHP;

        // ---------------- WORKFLOW CLASS ----------------
        $workflowContent = <<<PHP
        <?php

        namespace App\\Workflow;

        use Symfony\\Component\\Security\\Core\\User\\UserInterface;
        use Macc\\CommonBundle\\Workflow\\WorkflowActionInterface;
        use Macc\\CommonBundle\\Workflow\\AbstractWorkflowObject;
        use Macc\\CommonBundle\\Workflow\\WorkflowStateInterface;
        use Macc\\CommonBundle\\Workflow\\AbstractWorkflow;
        use App\\Enum\\{$name}States;
        use App\\Enum\\{$name}Actions;
        use Override;

        class {$name}Workflow extends AbstractWorkflow
        {
            /* Set this to true to enable checking if the user has permission
             * to execute the action before anything else occurs.
             * > Check {$name}Voter.php for more information.                  */
            protected bool \$security = false;

            // You can't directly use pure enums as array keys, you must convert to string using \$enum->value
            protected array \$transitions = [
                {$name}States::INIT->value => [
                    {$name}Actions::START->value => {$name}States::IN_PROGRESS->value,
                ],
                {$name}States::IN_PROGRESS->value => [
                    {$name}Actions::FINISH->value => {$name}States::FINISHED->value,
                    {$name}Actions::CANCEL->value => {$name}States::INIT->value,
                ],
                {$name}States::FINISHED->value => [],
            ];

            #[Override]
            protected function triggerPreTransitionHooks(
                AbstractWorkflowObject \$object,
                WorkflowActionInterface \$action,
                WorkflowStateInterface \$nextState,
                UserInterface|string \$user,
                ?object \$initiator = null,
                object ...\$contextObjects,
            ): bool {
                /**
                 * Modify this function to trigger actions before a transition is
                 * about to occur.
                 *
                 * Return false to abort the transition.
                 **/
                return true;
            }
        }
        PHP;

        // ---------------- VOTER CLASS ----------------
        $lowercasename = strtolower($name);
        $securityContent = <<<PHP
        <?php

        namespace App\\Security\\Voter\\App;

        use App\\Entity\\{$name};
        use App\\Entity\\Usuario;
        use App\\Enum\\{$name}Actions;
        use Symfony\\Component\\Security\\Core\\Authentication\\Token\\TokenInterface;
        use Symfony\\Component\\Security\\Core\\Authorization\\Voter\\Voter;

        class {$name}Voter extends Voter
        {
            protected function supports(string \$attribute, mixed \$subject): bool
            {
                if (!\$subject instanceof {$name}) {
                    return false;
                }

                \$allAttributes = array_merge(
                    array_map(fn(\$c) => \$c->value, {$name}Actions::cases())
                );

                return in_array(\$attribute, \$allAttributes, true);
            }

            protected function voteOnAttribute(string \$attribute, mixed \${$name}, TokenInterface \$token): bool
            {
                \$user = \$token->getUser();

                if (!\$user instanceof Usuario) {
                    return false;
                }

                // DIRECT ACCESS IF ADMIN
                if (in_array('ROLE_ADMINISTRADOR', \$user->getRoles(), true)) {
                    return true;
                }

                // Check user has action access
                if (in_array(\$attribute, array_map(fn(\$c) => \$c->value, {$name}Actions::cases()), true)) {
                    return \$user->hasFuncion("{$lowercasename}", {$name}Actions::from(\$attribute)->name);
                }

                return false;
            }
        }

        PHP;

        // ---------- CREAR ARCHIVOS ----------
        $fs->dumpFile($statesFile, $statesContent);
        $fs->dumpFile($actionsFile, $actionsContent);
        $fs->dumpFile($entityFile, $entityContent);
        $fs->dumpFile($entityHistoryFile, $entityHistoryContent);
        $fs->dumpFile($entityRepositoryFile, $repositoryContent);
        $fs->dumpFile($entityHistoryRepositoryFile, $historyRepositoryContent);
        $fs->dumpFile($workflowFile, $workflowContent);
        $fs->dumpFile($securityFile, $securityContent);

        $io->success("✅ Workflow '$name' succesfully generated. Files created:");
        $io->listing([
            $statesFile,
            $actionsFile,
            $entityFile,
            $entityHistoryFile,
            $entityRepositoryFile,
            $entityHistoryRepositoryFile,
            $workflowFile,
            $securityFile,
        ]);
        $io->info([
            'States and Actions: '
        ]);
        $io->listing([
            $statesFile,
            $actionsFile,
        ]);

        $io->info([
            'Workflow Entity: '
        ]);
        $io->listing([
            $entityFile,
            $entityHistoryFile,
        ]);

        $io->info([
            'Workflow Object: '
        ]);
        $io->listing([
            $workflowFile,
        ]);

        $io->info([
            'Voter: '
        ]);
        $io->listing([
            $securityFile,
        ]);

        return Command::SUCCESS;
    }
}
