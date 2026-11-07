<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Entity\Photo;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tout ce que le site sait d'un membre, dans un format réutilisable (droits d'accès et de portabilité du RGPD).
 * Si un nouveau champ personnel apparaît dans une entité, il faut l'ajouter ici.
 */
final class PersonalDataExporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exporte_le' => new \DateTimeImmutable()->format(\DATE_ATOM),
            'compte' => [
                'email' => $user->getEmail(),
                'nom_utilisateur' => $user->getName(),
                'ville' => $user->getCity(),
                'avatar' => $user->getAvatar(),
                'email_confirme' => $user->isVerified(),
                'emails_nouveau_concours' => $user->isNotifyNewTheme(),
                'compte_google_lie' => $user->getGoogleId(),
                'compte_facebook_lie' => $user->getFacebookId(),
                'inscrit_le' => $user->getCreatedAt()?->format(\DATE_ATOM),
                'dernier_vote_sondage' => $user->getLastVoteDate()?->format('Y-m-d'),
            ],
            'photos' => array_map(fn (Photo $photo): array => [
                'concours' => $photo->getTheme()?->getThemeChoice()?->getName(),
                'envoyee_le' => $photo->getCreatedAt()?->format(\DATE_ATOM),
                'validee' => $photo->isVisible(),
                'titre' => $photo->getTitle(),
                'description' => $photo->getDescription(),
                'image' => $this->urlGenerator->generate('app_photo_image', ['id' => $photo->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'votes_recus' => $photo->getVotes()->count(),
            ], $this->em->getRepository(Photo::class)->findBy(['user' => $user])),
            'votes' => array_map(static fn (Vote $vote): array => [
                'concours' => $vote->getTheme()?->getThemeChoice()?->getName(),
                'photo_de' => $vote->getPhoto()?->getUser()?->getName(),
                'le' => $vote->getDate()?->format(\DATE_ATOM),
            ], $this->em->getRepository(Vote::class)->findBy(['user' => $user])),
            'messages_de_contact' => array_map(static fn (Contact $contact): array => [
                'envoye_le' => $contact->getCreatedAt()?->format(\DATE_ATOM),
                'email' => $contact->getEmail(),
                'message' => $contact->getMessage(),
                'cloture_le' => $contact->getClosedAt()?->format(\DATE_ATOM),
            ], $this->contactMessages($user)),
        ];
    }

    /**
     * @return list<Contact>
     */
    private function contactMessages(User $user): array
    {
        return $this->em->getRepository(Contact::class)->createQueryBuilder('c')
            ->where('c.type = :type')
            ->andWhere('c.userId = :id OR c.email = :email')
            ->setParameter('type', Contact::TYPE_CONTACT)
            ->setParameter('id', $user->getId())
            ->setParameter('email', $user->getEmail())
            ->orderBy('c.id')
            ->getQuery()
            ->getResult();
    }
}
