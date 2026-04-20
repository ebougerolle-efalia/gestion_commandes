<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Créer l\'utilisateur admin initial')]
class SeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('admin-password', null, InputOption::VALUE_OPTIONAL, 'Mot de passe admin', 'admin123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRepo = $this->em->getRepository(User::class);

        if (!$userRepo->findOneBy(['username' => 'admin'])) {
            $user = new User();
            $user->setUsername('admin');
            $user->setRole('admin');
            $user->setPassword($this->hasher->hashPassword($user, $input->getOption('admin-password')));
            $this->em->persist($user);
            $this->em->flush();
            $output->writeln('<info>✓ Utilisateur admin créé (mot de passe: ' . $input->getOption('admin-password') . ')</info>');
        } else {
            $output->writeln('  Utilisateur admin existe déjà.');
        }

        $output->writeln('<info>Seed terminé.</info>');
        return Command::SUCCESS;
    }
}
