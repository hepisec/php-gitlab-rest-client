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

    public function getGroup(string $group) {
        return $this->get('/groups/' . $group);
    }

    public function createGroup(string $name, string $path) {
        return $this->post('/groups', [
                    'name' => $name,
                    'path' => $path,
                    'visibility' => 'private',
                    'project_creation_level' => 'developer'
        ]);
    }

    public function addGroupMember(string $group, int $memberId, int $accessLevel) {
        return $this->post('/groups/' . $group . '/members', [
            'user_id' => $memberId,
            'access_level' => $accessLevel
        ]);
    }

    public function findGroupProject(string $group, string $project) {
        return $this->get('/groups/' . $group . '/projects', [
                    'search' => $project
        ]);
    }

    public function findIssue(int $projectId, $title) {
        return $this->get('/projects/' . $projectId . '/issues', [
                    'search' => $title
        ]);
    }

    public function createIssue(int $projectId, array $issueData) {
        return $this->post('/projects/' . $projectId . '/issues', $issueData);
    }

    public function updateIssue(int $projectId, int $issueIid, array $issueData) {
        return $this->put('/projects/' . $projectId . '/issues/' . $issueIid, $issueData);
    }

    public function addTimeSpent(int $projectId, int $issueIid, string $timeSpent) {
        return $this->post('/projects/' . $projectId . '/issues/' . $issueIid . '/add_spent_time', [
                    'duration' => $timeSpent
        ]);
    }

    public function createIssueComment(int $projectId, int $issueIid, string $comment, ?\DateTimeInterface $createdAt = null) {
        if ($createdAt === null) {
            $createdAt = new \DateTime();
        }

        return $this->post('/projects/' . $projectId . '/issues/' . $issueIid . '/notes', [
                    'body' => $comment,
                    'created_at' => $createdAt->format('c')
        ]);
    }

    public function uploadAttachment(int $projectId, string $filePath) {
        $formFields = [
            'file' => DataPart::fromPath($filePath),
        ];

        $formData = new FormDataPart($formFields);

        return $this->request('POST', $this->getUrl('/projects/' . $projectId . '/uploads'), [
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                    'body' => $formData->bodyToIterable()
        ]);
    }

    public function getNamespace($name) {
        return $this->get('/namespaces/' . $name);
    }

    public function createProject(int $namespaceId, string $path) {
        return $this->post('/projects', [
                    'path' => $path,
                    'visibility' => 'private',
                    'namespace_id' => $namespaceId
        ]);
    }

    public function searchUsers($query) {
        return $this->get('/users', [
            'search' => $query
        ]);
    }

    private function getUrl($url) {
        return $this->baseUrl . '/api/v4' . $url;
    }

    private function get($url, array $params = [], array $options = []) {
        return $this->request('GET', $this->getUrl($url), array_merge($options, ['query' => $params]));
    }

    private function post($url, array $params = [], array $options = []) {
        return $this->request('POST', $this->getUrl($url), array_merge($options, ['body' => $params]));
    }

    private function put($url, array $params = [], array $options = []) {
        return $this->request('PUT', $this->getUrl($url), array_merge($options, ['body' => $params]));
    }

    private function request($method, $url, array $options = []) {
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
