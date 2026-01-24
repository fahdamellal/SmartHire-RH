<?php

namespace App\Modules\Embeddings\Services;

class SkillExtractor
{
    private array $dict = [

    // =========================
    // Programming Languages
    // =========================
    'java' => ['java', 'jdk', 'jre'],
    'kotlin' => ['kotlin'],
    'scala' => ['scala'],
    'groovy' => ['groovy'],

    'php' => ['php', 'php8', 'php 8', 'php7', 'php 7'],
    'javascript' => ['javascript', 'js', 'ecmascript', 'es6', 'es2015'],
    'typescript' => ['typescript', 'ts'],
    'python' => ['python', 'py'],
    'c' => ['c', 'langage c', 'language c'],
    'cpp' => ['c++', 'cpp', 'c plus plus'],
    'csharp' => ['c#', 'csharp', '.net c#', 'dotnet c#'],
    'go' => ['go', 'golang'],
    'rust' => ['rust'],
    'ruby' => ['ruby'],
    'swift' => ['swift'],
    'objectivec' => ['objective-c', 'objective c', 'objc'],
    'dart' => ['dart'],
    'r' => ['r', 'r language', 'langage r'],
    'matlab' => ['matlab'],
    'bash' => ['bash', 'shell', 'sh', 'shell scripting'],
    'powershell' => ['powershell', 'pwsh'],

    // =========================
    // Frontend (Web)
    // =========================
    'html' => ['html', 'html5'],
    'css' => ['css', 'css3'],
    'sass' => ['sass', 'scss'],
    'tailwind' => ['tailwind', 'tailwindcss', 'tailwind css'],
    'bootstrap' => ['bootstrap', 'bootstrap5', 'bootstrap 5', 'bootstrap4', 'bootstrap 4'],
    'materialui' => ['material ui', 'mui', 'material-ui', 'mui react'],
    'chakraui' => ['chakra', 'chakra ui', 'chakra-ui'],
    'antdesign' => ['ant design', 'antd', 'ant-design'],

    'react' => ['react', 'reactjs', 'react.js', 'react js'],
    'nextjs' => ['next', 'nextjs', 'next.js', 'next js'],
    'vue' => ['vue', 'vuejs', 'vue.js', 'vue js'],
    'nuxtjs' => ['nuxt', 'nuxtjs', 'nuxt.js'],
    'angular' => ['angular', 'angularjs', 'angular.js'],
    'svelte' => ['svelte', 'sveltejs', 'svelte.js'],
    'solidjs' => ['solid', 'solidjs', 'solid.js'],
    'jquery' => ['jquery', 'j क्वेरी', 'j query', 'jq'],

    'redux' => ['redux', 'redux toolkit', 'rtk', 'redux-toolkit'],
    'zustand' => ['zustand'],
    'reactquery' => ['react query', 'tanstack query', 'tanstack-query'],
    'rxjs' => ['rxjs', 'reactive extensions'],

    'vite' => ['vite'],
    'webpack' => ['webpack'],
    'babel' => ['babel'],
    'eslint' => ['eslint', 'eslintjs'],
    'prettier' => ['prettier'],

    // =========================
    // Backend / Frameworks
    // =========================
    'spring' => ['spring', 'spring framework'],
    'springboot' => ['springboot', 'spring boot', 'spring-boot'],
    'hibernate' => ['hibernate', 'jpa', 'jakarta persistence', 'java persistence api'],
    'maven' => ['maven', 'apache maven'],
    'gradle' => ['gradle'],

    'laravel' => ['laravel', 'laravel framework'],
    'symfony' => ['symfony', 'symfony framework'],
    'codeigniter' => ['codeigniter', 'code igniter'],
    'zend' => ['zend', 'laminas', 'zend framework', 'laminas framework'],

    'django' => ['django'],
    'flask' => ['flask'],
    'fastapi' => ['fastapi', 'fast api'],
    'celery' => ['celery'],
    'sqlalchemy' => ['sqlalchemy', 'sql alchemy'],

    'node' => ['node', 'nodejs', 'node.js'],
    'express' => ['express', 'expressjs', 'express.js'],
    'nestjs' => ['nestjs', 'nest', 'nest js', 'nest.js'],
    'koa' => ['koa', 'koajs', 'koa.js'],
    'fastify' => ['fastify'],
    'hapi' => ['hapi', 'hapijs', 'hapi.js'],

    'dotnet' => ['.net', 'dotnet', 'asp.net', 'aspnet', 'asp.net core', 'aspnet core'],
    'entityframework' => ['entity framework', 'entityframework', 'ef core', 'efcore'],
    'wpf' => ['wpf'],
    'winforms' => ['winforms', 'windows forms'],

    'rails' => ['rails', 'ruby on rails', 'ror'],
    'sinatra' => ['sinatra'],

    'gin' => ['gin', 'gin-gonic', 'gin gonic'],
    'fiber' => ['fiber', 'gofiber', 'go fiber'],

    // =========================
    // APIs / Architecture
    // =========================
    'rest' => ['rest', 'rest api', 'api rest', 'restful', 'restful api'],
    'graphql' => ['graphql', 'gql'],
    'grpc' => ['grpc'],
    'soap' => ['soap', 'wsdl'],
    'websocket' => ['websocket', 'web socket', 'ws'],
    'openapi' => ['openapi', 'swagger', 'swagger ui', 'swaggerui'],

    'microservices' => ['microservices', 'micro-service', 'micro services'],
    'monolith' => ['monolith', 'monolithic'],
    'soa' => ['soa', 'service oriented architecture', 'service-oriented architecture'],
    'ddd' => ['ddd', 'domain driven design', 'domain-driven design'],
    'cleanarchitecture' => ['clean architecture', 'hexagonal', 'ports and adapters', 'onion architecture'],
    'eventdriven' => ['event driven', 'event-driven', 'eda', 'event driven architecture'],

    // =========================
    // Databases / Storage
    // =========================
    'sql' => ['sql'],
    'postgresql' => ['postgres', 'postgresql', 'postgre', 'postgre sql', 'psql'],
    'mysql' => ['mysql'],
    'mariadb' => ['mariadb', 'maria db'],
    'sqlite' => ['sqlite', 'sqlite3'],
    'mssql' => ['sql server', 'mssql', 'ms sql', 'microsoft sql server'],
    'oracle' => ['oracle', 'oracle db', 'oracle database', 'plsql', 'pl/sql'],
    'db2' => ['db2', 'ibm db2'],

    'mongodb' => ['mongodb', 'mongo', 'mongo db', 'mongo-db'],
    'redis' => ['redis'],
    'elasticsearch' => ['elasticsearch', 'elastic search', 'elastic stack', 'elk'],
    'opensearch' => ['opensearch', 'open search'],
    'cassandra' => ['cassandra'],
    'couchdb' => ['couchdb', 'couch db'],
    'dynamodb' => ['dynamodb', 'dynamo db'],
    'firebase' => ['firebase', 'firestore', 'cloud firestore', 'realtime database'],

    'rabbitmq' => ['rabbitmq', 'rabbit mq'],
    'kafka' => ['kafka', 'apache kafka'],
    'activemq' => ['activemq', 'active mq'],
    'nats' => ['nats', 'nats.io'],

    's3' => ['s3', 'amazon s3'],
    'minio' => ['minio', 'mini o'],
    'ceph' => ['ceph'],

    // Vector DB / Search (utile pour SmartHire)
    'pgvector' => ['pgvector', 'pg vector', 'vector extension'],
    'pinecone' => ['pinecone'],
    'weaviate' => ['weaviate'],
    'qdrant' => ['qdrant'],
    'milvus' => ['milvus'],
    'faiss' => ['faiss'],

    // =========================
    // DevOps / Cloud / Containers
    // =========================
    'docker' => ['docker', 'dockerfile'],
    'dockercompose' => ['docker compose', 'docker-compose', 'dockercompose'],
    'kubernetes' => ['kubernetes', 'k8s'],
    'helm' => ['helm', 'helm charts', 'helm chart'],
    'terraform' => ['terraform', 'tf'],
    'ansible' => ['ansible'],
    'packer' => ['packer'],

    'linux' => ['linux', 'ubuntu', 'debian', 'centos', 'redhat', 'rhel'],
    'nginx' => ['nginx', 'engine x'],
    'apache' => ['apache', 'apache http server', 'httpd'],
    'tomcat' => ['tomcat', 'apache tomcat'],
    'iis' => ['iis', 'internet information services'],

    'aws' => ['aws', 'amazon web services'],
    'ec2' => ['ec2', 'amazon ec2'],
    'lambda' => ['lambda', 'aws lambda'],
    'azure' => ['azure', 'microsoft azure'],
    'gcp' => ['gcp', 'google cloud', 'google cloud platform'],
    'cloudflare' => ['cloudflare', 'cloud flare'],

    // CI/CD
    'cicd' => ['ci/cd', 'cicd', 'continuous integration', 'continuous delivery', 'continuous deployment'],
    'jenkins' => ['jenkins'],
    'githubactions' => ['github actions', 'githubactions'],
    'gitlabci' => ['gitlab ci', 'gitlab-ci', 'gitlab pipelines'],
    'circleci' => ['circleci', 'circle ci'],
    'travis' => ['travis', 'travis ci'],
    'azuredevops' => ['azure devops', 'ado', 'azure pipelines'],

    // =========================
    // Version Control / Collaboration
    // =========================
    'git' => ['git'],
    'github' => ['github', 'git hub'],
    'gitlab' => ['gitlab', 'git lab'],
    'bitbucket' => ['bitbucket', 'bit bucket'],
    'svn' => ['svn', 'subversion'],

    'jira' => ['jira'],
    'confluence' => ['confluence'],
    'trello' => ['trello'],
    'notion' => ['notion'],
    'slack' => ['slack'],
    'teams' => ['microsoft teams', 'teams'],

    // =========================
    // Testing / QA
    // =========================
    'unittest' => ['unit test', 'unit testing', 'unittest'],
    'integrationtest' => ['integration test', 'integration testing'],
    'e2e' => ['e2e', 'end to end', 'end-to-end'],

    'junit' => ['junit', 'junit5', 'junit 5'],
    'testng' => ['testng'],
    'mockito' => ['mockito'],
    'phpunit' => ['phpunit'],
    'pest' => ['pest', 'pestphp'],
    'pytest' => ['pytest', 'py test'],
    'nose' => ['nose', 'nosetests'],
    'jest' => ['jest'],
    'mocha' => ['mocha'],
    'chai' => ['chai'],
    'cypress' => ['cypress'],
    'playwright' => ['playwright'],
    'selenium' => ['selenium'],
    'postman' => ['postman'],
    'newman' => ['newman'],
    'k6' => ['k6'],
    'jmeter' => ['jmeter', 'apache jmeter'],

    // =========================
    // Security
    // =========================
    'owasp' => ['owasp', 'owasp top 10', 'owasp top10'],
    'jwt' => ['jwt', 'json web token'],
    'oauth2' => ['oauth2', 'oauth 2', 'oauth'],
    'openidconnect' => ['openid connect', 'oidc'],
    'sso' => ['sso', 'single sign-on', 'single sign on'],

    'tls' => ['tls', 'ssl', 'https'],
    'cryptography' => ['cryptography', 'crypto', 'encryption', 'hashing', 'bcrypt', 'argon2'],

    'pentest' => ['pentest', 'pen test', 'penetration testing'],
    'vulnerability' => ['vulnerability management', 'vuln management'],

    // =========================
    // Data / AI / ML (utile pour CV IT modernes)
    // =========================
    'datascience' => ['data science', 'datascience'],
    'machinelearning' => ['machine learning', 'ml'],
    'deeplearning' => ['deep learning', 'dl'],
    'nlp' => ['nlp', 'natural language processing'],
    'computervision' => ['computer vision', 'cv', 'vision par ordinateur'],

    'numpy' => ['numpy'],
    'pandas' => ['pandas'],
    'matplotlib' => ['matplotlib'],
    'scikitlearn' => ['scikit-learn', 'sklearn', 'scikit learn'],
    'tensorflow' => ['tensorflow', 'tf'],
    'keras' => ['keras'],
    'pytorch' => ['pytorch', 'torch'],
    'opencv' => ['opencv', 'open cv'],
    'yolov8' => ['yolov8', 'yolo v8', 'ultralytics'],

    // LLM / GenAI
    'llm' => ['llm', 'large language model'],
    'openai' => ['openai', 'chatgpt', 'gpt'],
    'azureopenai' => ['azure openai', 'openai azure', 'azureopenai'],
    'langchain' => ['langchain', 'lang chain'],
    'llamaindex' => ['llamaindex', 'llama index'],
    'rag' => ['rag', 'retrieval augmented generation', 'retrieval-augmented generation'],
    'embeddings' => ['embeddings', 'embedding'],
    'promptengineering' => ['prompt engineering', 'prompting'],

    // =========================
    // Observability / Monitoring
    // =========================
    'logging' => ['logging', 'log management'],
    'prometheus' => ['prometheus'],
    'grafana' => ['grafana'],
    'elk' => ['elk', 'elastic stack', 'logstash', 'kibana'],
    'datadog' => ['datadog', 'data dog'],
    'newrelic' => ['new relic', 'newrelic'],
    'sentry' => ['sentry'],

    // =========================
    // Networking (IT skills)
    // =========================
    'tcpip' => ['tcp/ip', 'tcp ip', 'tcpip'],
    'dns' => ['dns'],
    'dhcp' => ['dhcp'],
    'http' => ['http', 'https'],
    'vpn' => ['vpn'],
    'firewall' => ['firewall', 'iptables', 'ufw'],
    'loadbalancing' => ['load balancing', 'loadbalancer', 'lb'],

    // =========================
    // Methodologies / Practices
    // =========================
    'agile' => ['agile'],
    'scrum' => ['scrum'],
    'kanban' => ['kanban'],
    'devops' => ['devops', 'dev ops'],
    'sre' => ['sre', 'site reliability engineering'],
    'tdd' => ['tdd', 'test driven development', 'test-driven development'],
    'bdd' => ['bdd', 'behavior driven development', 'behaviour driven development'],
    'solid' => ['solid'],
    'designpatterns' => ['design patterns', 'design-patterns', 'patterns'],
    'uml' => ['uml', 'unified modeling language'],
    'c4model' => ['c4', 'c4 model', 'c4 diagram', 'c4 diagrams'],
    'gitflow' => ['gitflow', 'git flow'],

    // =========================
    // Mobile / Desktop
    // =========================
    'android' => ['android', 'android sdk'],
    'ios' => ['ios'],
    'flutter' => ['flutter'],
    'reactnative' => ['react native', 'react-native', 'reactnative'],
    'xamarin' => ['xamarin'],
    'electron' => ['electron', 'electronjs', 'electron.js'],

    // =========================
    // CMS / ERP / Misc
    // =========================
    'wordpress' => ['wordpress', 'wp'],
    'drupal' => ['drupal'],
    'shopify' => ['shopify'],
    'magento' => ['magento', 'adobe commerce'],
    'prestashop' => ['prestashop', 'presta shop'],

    // =========================
    // Build / Package / Runtime
    // =========================
    'npm' => ['npm'],
    'yarn' => ['yarn'],
    'pnpm' => ['pnpm'],
    'composer' => ['composer'],
    'pip' => ['pip', 'pip3'],
    'poetry' => ['poetry'],
    'conda' => ['conda', 'anaconda', 'miniconda'],

];

    public function extract(string $text): array
    {
        $t = mb_strtolower($text);

        $found = [];

        foreach ($this->dict as $label => $aliases) {
            foreach ($aliases as $a) {
                $a = mb_strtolower(trim($a));
                if ($a === '') continue;

                // match mot/terme
                if (preg_match('/\b' . preg_quote($a, '/') . '\b/u', $t)) {
                    $found[] = $label;
                    break; // label trouvé, pas besoin des autres alias
                }
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }
}
