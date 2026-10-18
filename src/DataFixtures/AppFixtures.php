<?php

namespace App\DataFixtures;

use App\Entity\Photo;
use App\Entity\SurveyChoice;
use App\Entity\Theme;
use App\Entity\User;
use DateMalformedStringException;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    /**
     * @throws DateMalformedStringException
     */
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $isAdmin = true;
        $userCount = 12;

        //First Theme :
        $themeChoice = new SurveyChoice()
            ->setNumberVoter($userCount)
            ->setName('Symétries naturelles')
            ->setDescription("La nature fait tellement bien les chôses")
        ;

        $theme = new Theme()
            ->setIsAlreadyActived(true)
            ->setDateStart(new \DateTime()->modify('first day of this month'))
            ->setDateEnd(new \DateTime()->modify('last day of this month'))
            ->setDirectoryName('first_theme')
            ->setThemeChoice($themeChoice)
        ;

        $manager->persist($theme);

        for ($i = 0; $i < $userCount; $i++) {
            $user = new User();
            $photo = new Photo();
            $user->setCreatedAtValue();

            $user
                ->setEmail(($isAdmin) ?'alexis.verquin@gmail.com' : $faker->email)
                ->setRoles(($isAdmin) ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'])
                ->setName(($isAdmin) ? 'Alexis' : $faker->name)
                ->setIsActive(true)
                ->setIsVerified(true)
                ->setPassword(
                    $this->passwordHasher->hashPassword(
                        $user,
                        "Password123!"
                    )
                )
            ;

            $photo->setCreatedAtValue();
            $photo
                ->setIsVisible(true)
                ->setName($i.'.jpg')
                ->setTheme($theme)
            ;
            $user->addPhoto($photo);
            $isAdmin = false;
            $manager->persist($user);

        }
        $manager->flush();
    }
}
