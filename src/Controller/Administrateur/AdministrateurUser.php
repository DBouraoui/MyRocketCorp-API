<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Administrateur;

use App\DTO\User\UserDeleteDTO;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/administrateur/user', name: 'user_register')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurUser extends AbstractController
{
    public const GET_ONE_USER = 'getOneUser';

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly UserService $userService,
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route(name: '_post', methods: ['POST'])]
    public function post(RequestStack $requestStack): JsonResponse
    {
        try {
            $userResponse = $this->userService->createUserFromRequest($requestStack);

            return $this->json($userResponse, Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error('Error creating user', ['error' => $e->getMessage()]);

            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: '_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        try {
            $criteria = $request->query->get('criteria');
            $all = $request->query->get('all');

            if (!empty($criteria) && empty($all)) {
                $cacheKey = self::GET_ONE_USER.$criteria;
                $userFinding = $this->cache->get($cacheKey, function () use ($criteria) {
                    return $this->userRepository->getOneUserByCriteria($criteria);
                });

                if (empty($userFinding)) {
                    throw new \Exception(UserService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
                }

                return $this->json(
                    ['success' => true, 'data' => $this->userService->normalizeUserObject($userFinding)],
                    Response::HTTP_OK
                );
            }

            if (!empty($all) && empty($criteria)) {
                $userFinding = $this->cache->get(self::GET_ALL_USERS, function (ItemInterface $item) {
                    $item->expiresAfter(3600);

                    return $this->userRepository->findAll();
                });

                return $this->json(
                    $this->userService->normalizeUsersObjects($userFinding),
                    Response::HTTP_OK
                );
            }

            return $this->json(
                ['success' => false, 'message' => 'Aucun critère valide fourni'],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des utilisateurs', ['error' => $e->getMessage()]);

            return $this->json(
                ['success' => false, 'message' => 'Erreur serveur interne'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Erreur lors de la récupération des utilisateurs', ['error' => $e->getMessage()]);

            return $this->json(
                ['success' => false, 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route(path: '/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        try {
            if (empty($uuid)) {
                return $this->json(
                    ['success' => false, 'message' => 'UUID utilisateur requis'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $deleteDTO = new UserDeleteDTO();
            $deleteDTO->uuid = $uuid;

            $violations = $this->validator->validate($deleteDTO);

            if (count($violations) > 0) {
                $error = [];
                foreach ($violations as $violation) {
                    $error[] = $violation->getMessage();
                }

                return $this->json($error, Response::HTTP_BAD_REQUEST);
            }

            $customer = $this->userRepository->findOneBy(['uuid' => $uuid]);

            if (empty($customer)) {
                return $this->json(
                    ['success' => false, 'message' => 'Utilisateur non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $this->entityManager->remove($customer);
            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALL_USERS);

            return $this->json(
                ['success' => true, 'message' => 'Utilisateur supprimé avec succès'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression de l'utilisateur", ['error' => $e->getMessage()]);

            return $this->json(
                ['success' => false, 'message' => 'Erreur serveur interne'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
