<?php

namespace Rundum\Gitlab;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * Description of Client
 *
 * @author hendrik
 */
class Client {

    const ACCESS_LEVEL_OWNER = 50;

    private $baseUrl;
    private $token;

    public function __construct(string $baseUrl, string $token) {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    public function getGroup(string $group): array|bool {
        return $this->get('/groups/' . $group);
    }

    public function createGroup(string $name, string $path): array|bool {
        return $this->post('/groups', [
                    'name' => $name,
                    'path' => $path,
                    'visibility' => 'private',
                    'project_creation_level' => 'developer'
        ]);
    }

    public function addGroupMember(string $group, int $memberId, int $accessLevel): array|bool {
        return $this->post('/groups/' . $group . '/members', [
                    'user_id' => $memberId,
                    'access_level' => $accessLevel
        ]);
    }

    public function findGroupProject(string $group, string $project): array|bool {
        return $this->get('/groups/' . $group . '/projects', [
                    'search' => $project
        ]);
    }

    public function getIssues(int $projectId, $page = 1, $perPage = 20): array|bool {
        return $this->get('/projects/' . $projectId . '/issues', [
                    'page' => $page,
                    'per_page' => $perPage
        ]);
    }

    public function findIssue(int $projectId, $title): array|bool {
        return $this->get('/projects/' . $projectId . '/issues', [
                    'search' => $title
        ]);
    }

    public function createIssue(int $projectId, array $issueData): array|bool {
        return $this->post('/projects/' . $projectId . '/issues', $issueData);
    }

    public function updateIssue(int $projectId, int $issueIid, array $issueData): array|bool {
        return $this->put('/projects/' . $projectId . '/issues/' . $issueIid, $issueData);
    }

    public function getLabels(int $projectId): array|bool {
        return $this->get('/projects/' . $projectId . '/labels');
    }

    public function addTimeSpent(int $projectId, int $issueIid, string $timeSpent): array|bool {
        return $this->post('/projects/' . $projectId . '/issues/' . $issueIid . '/add_spent_time', [
                    'duration' => $timeSpent
        ]);
    }

    public function createIssueComment(int $projectId, int $issueIid, string $comment, ?\DateTimeInterface $createdAt = null): array|bool {
        if ($createdAt === null) {
            $createdAt = new \DateTime();
        }

        return $this->post('/projects/' . $projectId . '/issues/' . $issueIid . '/notes', [
                    'body' => $comment,
                    'created_at' => $createdAt->format('c')
        ]);
    }

    public function uploadAttachment(int $projectId, string $filePath): array|bool {
        $formFields = [
            'file' => DataPart::fromPath($filePath),
        ];

        $formData = new FormDataPart($formFields);

        return $this->request('POST', $this->getUrl('/projects/' . $projectId . '/uploads'), [
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                    'body' => $formData->bodyToIterable()
        ]);
    }

    public function getNamespace($name): array|bool {
        return $this->get('/namespaces/' . $name);
    }

    public function getProjects($page = 1, $perPage = 20): array|bool {
        return $this->get('/projects', [
                    'page' => $page,
                    'per_page' => $perPage
        ]);
    }

    public function createProject(int $namespaceId, string $path): array|bool {
        return $this->post('/projects', [
                    'path' => $path,
                    'visibility' => 'private',
                    'namespace_id' => $namespaceId
        ]);
    }

    public function searchUsers($query): array|bool {
        return $this->get('/users', [
                    'search' => $query
        ]);
    }

    private function getUrl($url): string {
        return $this->baseUrl . '/api/v4' . $url;
    }

    private function get($url, array $params = [], array $options = []): array|bool {
        return $this->request('GET', $this->getUrl($url), array_merge($options, ['query' => $params]));
    }

    private function post($url, array $params = [], array $options = []): array|bool {
        return $this->request('POST', $this->getUrl($url), array_merge($options, ['body' => $params]));
    }

    private function put($url, array $params = [], array $options = []): array|bool {
        return $this->request('PUT', $this->getUrl($url), array_merge($options, ['body' => $params]));
    }

    private function request($method, $url, array $options = []): array|bool {
        $options = array_merge_recursive($options, [
            'headers' => [
                'PRIVATE-TOKEN' => $this->token
            ]
        ]);
        $client = HttpClient::create();
        $response = $client->request($method, $url, $options);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            try {
                var_dump($response->getContent());
            } catch (\Exception $ex) {
                echo 'Error on request ' . $method . ' ' . $url . "\n";
                echo $ex->getMessage() . "\n";
            }

            return false;
        }

        return json_decode($response->getContent(), true);
    }

}
