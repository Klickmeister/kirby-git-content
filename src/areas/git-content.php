<?php

use Thathoff\GitContent\KirbyGitHelper;
use Kirby\Http\Remote;

return [
    'label' => option('thathoff.git-content.menuLabel', 'Git Content'),
    'icon'  => option('thathoff.git-content.menuIcon', 'sitemap'),
    'menu'  => true,
    'link'  => 'git-content',
    'dialogs' => [
        'git-content.revert' => [
            'pattern' => 'git-content/revert',
            'load' => fn () => [
                'component' => 'k-remove-dialog',
                'props' => [
                    'text' => "Are you sure you want to revert all changes?<br><br>⚠️ This cannot be undone.",
                    'submitButton' => 'Revert changes',
                    'icon' => 'undo',
                ]
            ],
            'submit' => function () {
                $git = new KirbyGitHelper();
                $git->reset();
                $git->clean();

                return true;
            }
        ],
        'git-content.commit' => [
            'pattern' => 'git-content/commit',
            'load' => fn () => [
                'component' => 'k-form-dialog',
                'props' => [
                    'fields' => [
                        'title' => [
                            'label'    => "Title",
                            'type'     => 'text',
                            'counter'  => true,
                            'maxlength' => 72,
                            'required' => true,
                        ],
                        'description' => [
                            'label'    => "Description",
                            'type'     => 'textarea',
                            'buttons'  => false,
                            'required' => false,
                        ],
                    ],
                    'size' => 'large'
                ]
            ],
            'submit' => function () {
                $message = get('title');
                $description = get('description');

                if ($description) {
                    $message .= "\n\n" . $description;
                }

                $git = new KirbyGitHelper();
                $git->addAll();
                $git->commit($message, null, $git->getAuthorString());

                return true;
            }
        ],
        'git-content.switchBranch' => [
            'pattern' => 'git-content/branch',
            'load' => function () {
                $git = new KirbyGitHelper();
                $branches = $git->getBranches();
                $currentBranch = $git->getCurrentBranch();

                $branchesOptions = [];

                foreach ($branches as $branch) {
                    $branchesOptions[] = [
                        'value' => $branch,
                        'text' => $branch,
                    ];
                }

                return [
                    'component' => 'k-form-dialog',
                    'props' => [
                        'fields' => [
                            'branch' => [
                                'label'     => "Branch",
                                'type'      => 'select',
                                'options'   => $branchesOptions,
                                'empty'     => false,
                                'required'  => true,
                                'help'      => "Switching branches might take a while."
                            ]
                        ],
                        'value' => [
                            'branch' => $currentBranch,
                        ],
                        'submitButton' => 'Switch Branch',
                    ],
                ];
            },
            'submit' => function () {
                $branchName = get('branch');

                $git = new KirbyGitHelper();
                $git->checkout($branchName);
                return true;
            }
        ],
        'git-content.createBranch' => [
            'pattern' => 'git-content/create-branch',
            'load' => fn () => [
                'component' => 'k-form-dialog',
                'props' => [
                    'fields' => [
                        'branch' => [
                            'label'     => "Branch",
                            'type'      => 'slug',
                            'empty'     => false,
                            'required'  => true,
                        ]
                    ],
                    'submitButton' => 'Create Branch',
                ],
            ],
            'submit' => function () {
                $branchName = get('branch');

                $git = new KirbyGitHelper();
                $git->createBranch($branchName);
                return true;
            }
        ],
        'git-content.deployToProd' => [
            'pattern' => 'git-content/deploy-to-prod',
            'load' => fn () => [
                'component' => 'k-remove-dialog',
                'props' => [
                    'text' => 'Are you sure you want to deploy content to production?<br><br>This will pull the latest changes on the live server.',
                    'submitButton' => 'Deploy to production',
                    'icon' => 'server',
                ]
            ],
            'submit' => function () {
                $prodUrl = option('thathoff.git-content.prodUrl', '');
                $secret = option('thathoff.git-content.cronHooksSecret', '');
                
                if (empty($prodUrl)) {
                    throw new Exception('Live URL is not configured');
                }
                
                // create the URL for the remote call
                $url = $secret ? 
                    $prodUrl . '/git-content/pull?secret=' . urlencode($secret) : 
                    $prodUrl . '/git-content/pull';
                
                // make the remote call
                try {
                    $response = Remote::get($url);
                    
                    if ($response->code() !== 200) {
                        throw new Exception('Failed to deploy: ' . $response->content());
                    }
                    
                    return [
                        'message' => 'Content successfully deployed to live site'
                    ];
                } catch (Exception $e) {
                    throw new Exception('Error connecting to live site: ' . $e->getMessage());
                }
            }
        ],
    ],
    'views' => [
        [
            'pattern' => 'git-content',
            'action'  => function () {
                $git = new Thathoff\GitContent\KirbyGitHelper();

                $logFormatted = array_map(
                    function ($entry) {
                        return [
                            'hash'    => $entry['hash'],
                            'message' => $entry['message'],
                            'date'    => $entry['date']->format(DateTime::ISO8601),
                            'author'  => $entry['author'],
                            'email'  => $entry['email'],
                        ];
                    },
                    $git->log()
                );

                return [
                    'component' => 'git-content',
                    'title' => 'Git Content',
                    'props' => [
                        'disableBranchManagement' => (bool)option('thathoff.git-content.disableBranchManagement', false),
                        'log' => $logFormatted,
                        'helpText' => option('thathoff.git-content.helpText'),
                        'branch' => $git->getCurrentBranch(),
                        'status' => $git->status(), // is associative array consisting of changed files and whether repo is ahead/behind to origin
                        'allowPush' => (bool)option('thathoff.git-content.allowPush', true),
                        'allowPull' => (bool)option('thathoff.git-content.allowPull', true),
                        'prodUrl' => option('thathoff.git-content.prodUrl', ''),
                    ],
                ];
            }
        ],
    ],
];
