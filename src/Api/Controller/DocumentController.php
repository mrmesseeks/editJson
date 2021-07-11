<?php

namespace App\Api\Controller;

use App\Repository\DocumentRepository;
use DateTime;
use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentController extends AbstractController
{
    protected SerializerInterface $serializer;

    protected EntityManagerInterface $objectManager;

    /**
     * DocumentController constructor.
     *
     * @param SerializerInterface    $serializer
     * @param EntityManagerInterface $objectManager
     */
    public function __construct(
        SerializerInterface $serializer,
        EntityManagerInterface $objectManager
    )
    {
        $this->serializer = $serializer;
        $this->objectManager = $objectManager;
    }

    public function createDocument(): JsonResponse
    {
        $document      = new Document();
        $entityManager = $this->getDoctrine()->getManager();

        $currentDate = new DateTime();

        $document
            ->setStatus('draft')
            ->setCreatedAt($currentDate)
            ->setUpdatedAt($currentDate);

        $entityManager->persist($document);
        $entityManager->flush();

        $response = [
            'document' => [
                'id'       => $document->getId(),
                'status'   => $document->getStatus(),
                'payload'  => json_encode([]),
                'createAt' => $document->getCreatedAt()->format('Y-m-d H:i:sP'),
                'modifyAt' => $document->getUpdatedAt()->format('Y-m-d H:i:sP'),
            ],
        ];

        return new JsonResponse($response);
    }

    public function updateDocument(string $id, Request $request, DocumentRepository $documentRepository): JsonResponse
    {
        try {
            $document = $documentRepository
                ->find($id);

            if (!$document) {
                throw $this->createNotFoundException(
                    'No product found for id ' . $id
                );
            }

            $this->updatePayload($document, $request->getContent());

            //TODO $response создавать отдельно
            $response = [
                'document' => [
                    'id'       => $document->getId(),
                    'status'   => $document->getStatus(),
                    'payload'  => $document->getPayload() ?? [],
                    'createAt' => $document->getCreatedAt()->format('Y-m-d H:i:sP'),
                    'modifyAt' => $document->getUpdatedAt()->format('Y-m-d H:i:sP'),
                ],
            ];

            return $this->json(
                $response
            );
        } catch (NotFoundHttpException $exception) {
            return $this->json(
                [
                    'error' => $exception->getMessage(),
                ],
                Response::HTTP_NOT_FOUND
            );
        }
    }

    public function publishDocument(Request $request, DocumentRepository $documentRepository): JsonResponse
    {
        $document = $documentRepository
            ->find($request->get('id'));

        //TODO Перенести в update
        $document->setStatus('published');
        $currentDate = new DateTime();
        $document->setUpdatedAt($currentDate);

        $this->objectManager->persist($document);
        $this->objectManager->flush();

        //TODO слушатель?
        $response = [
            'document' => [
                'id'       => $document->getId(),
                'status'   => $document->getStatus(),
                'payload'  => $document->getPayload() ?? [],
                'createAt' => $document->getCreatedAt()->format('Y-m-d H:i:sP'),
                'modifyAt' => $document->getUpdatedAt()->format('Y-m-d H:i:sP'),
            ],
        ];

        return new JsonResponse($response);
    }

    public function getDocument(Request $request, DocumentRepository $documentRepository): JsonResponse
    {
        //TODO Исправить пагинацию
        $page       = $request->query->get('page') ?? 1;
        $perPage    = $request->query->get('perPage') ?? 20;
        $firstDoc = ($page-1) * $perPage;

        $documents = $documentRepository
            ->findWithPagination($firstDoc, $perPage);

        $totalPage = ceil(count($documents)/$perPage);

        $response   = [];
        foreach ($documents as $document) {
            $response[] = [
                'document' => [
                    'id'       => $document->getId(),
                    'status'   => $document->getStatus(),
                    'payload'  => $document->getPayload() ?? [],
                    'createAt' => $document->getCreatedAt()->format('Y-m-d H:i:sP'),
                    'modifyAt' => $document->getUpdatedAt()->format('Y-m-d H:i:sP'),
                ],
            ];
        }

        $response[] = [
            'pagination' => [
                'page'    => $page,
                'prePage' => $perPage,
                'total'   => $totalPage,
            ],
        ];

        return $this->json(
            $response
        );
    }

    private function updatePayload(Document $document, string $content): void
    {
        if ($document->getStatus() === 'published') {
            throw new BadRequestHttpException('Document published');
        }

        //TODO Реализовать нормалайзер??
        $payload = json_decode($content, true)['document'];
        $document->setPayload($payload['payload']);
        $currentDate = new DateTime();
        $document->setUpdatedAt($currentDate);

        $this->objectManager->persist($document);
        $this->objectManager->flush();
    }
}