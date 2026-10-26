# F.O.G.O. // Fiscalização, Operação e Gestão Otimizada

> **Plataforma Municipal de Inteligência Urbana e Telemetria para o Ecossistema PROMETHEUS**  
> Desenvolvido para catalogação de ativos IoT, monitoramento geoespacial em tempo real e apoio preditivo à tomada de decisão para segurança pública e mobilidade urbana na região de Marília/SP.

---

## 📋 Sumário
- [Visão Geral da Solução](#-visão-geral-da-solução)
- [Decisões de Engenharia & Justificativas Técnicas](#-decisões-de-engenharia--justificativas-técnicas)
  - [Por que PHP e SQLite?](#por-que-php-e-sqlite)
  - [Arquitetura de Instalação e População Autocontida (setup.php)](#arquitetura-de-instalação-e-população-autocontida-setupphp)
  - [Por que Leaflet.js e Tiles Livres do OpenStreetMap?](#por-que-leafletjs-e-tiles-livres-do-openstreetmap)
  - [Como Funcionam as Previsões do PROMETHEUS (Fase 3)](#como-funcionam-as-previsões-do-prometheus-fase-3)
- [Conformidade com o Desafio (Fases 1, 2 e 3)](#-conformidade-com-o-desafio)
- [Estrutura de Diretórios](#-estrutura-de-diretórios)
- [Instalação e Execução Passo a Passo](#-instalação-e-execução-passo-a-passo)
- [Credenciais de Demonstração](#-credenciais-de-demonstração)

---

## 🚀 Visão Geral da Solução

O **F.O.G.O.** atua como a camada municipal de ingestão, visualização operacional e inferência preditiva integrada ao sistema **PROMETHEUS**. A solução centraliza o inventário de ativos inteligentes da cidade (radares, câmeras PTZ, viaturas, patinetes IoT e balanças rodoviárias), rastreia agentes no terreno e prevê pontos críticos antes que gerem estrangulamentos ou falhas na malha viária urbana.

---

## 💡 Decisões de Engenharia & Justificativas Técnicas

### Por que PHP e SQLite?
* **Ecossistema Regional:** A escolha do **PHP** baseia-se na sua consolidação no polo de tecnologia de Marília e região, onde existe ampla disponibilidade de profissionais, facilidade de implantação em servidores legados ou modernos e uma curva de desenvolvimento ágil e confortável para a equipe.
* **Leveza e Portabilidade com SQLite:** A utilização do SQLite elimina a necessidade de instalar e configurar instâncias externas pesadas de SGBDs (como MySQL ou PostgreSQL) para avaliação do desafio. Com o driver `pdo_sqlite` ativado nativamente no PHP, o sistema torna-se imediatamente portátil e com garantia de integridade referencial via chaves estrangeiras (`PRAGMA foreign_keys = ON;`).

### Arquitetura de Instalação e População Autocontida (`setup.php`)
Em vez de depender de scripts de migração manuais ou arquivos `.sql` externos importados via terminal/painel administrativo, optou-se por embutir a DDL (Data Definition Language) e a massa de testes completa dentro do script `setup.php`.
* **Zero Configuração:** Qualquer avaliador ou desenvolvedor inicializa o banco de dados operacional com 1 único clique no navegador ou comando no terminal.
* **Reprodutibilidade:** Facilita o reset rápido da base de testes para simular novamente cenários críticos, furtos e manutenções emergenciais.

### Por que Leaflet.js e Tiles Livres do OpenStreetMap?
Para o mapa operacional do C.I.O. (Fase 2), optou-se pela biblioteca **Leaflet.js** consumindo azulejos cartográficos diretos do **OpenStreetMap** (OSM), com tratamento de contraste escuro via CSS:
* **Independência de Chaves de API:** APIs comerciais (como Google Maps ou Mapbox) exigem cadastro de cartões de crédito e chaves com cotas restritas. O OSM garante funcionamento perene, gratuito e alinhado aos padrões abertos da administração pública.
* **Alto Desempenho:** Renderização vetorial leve para marcação de viaturas, patinetes e equipamentos sem consumo excessivo de memória da estação de trabalho do C.I.O.

### Como Funcionam as Previsões do PROMETHEUS (Fase 3)
A inteligência preditiva foi construída como um motor analítico **explicável e auditável**, evitando modelos de "caixa-preta":
1. **Índice de Vulnerabilidade Operacional por Região (IVOR):**
   * **Cálculo:** Pondera a taxa de indisponibilidade de equipamentos no bairro, a quantidade de manutenções com criticidade `imediata` e o déficit de agentes em serviço na respectiva região.
   * **Objetivo:** Destacar bairros com alto risco de pontos cegos de fiscalização (ex.: Centro ou Cascata).
2. **Previsão de Pane de Hardware (Janela de 7 Dias):**
   * **Cálculo:** Avalia a taxa histórica de quebras registradas no `historico_manutencoes` cruzada com a proximidade da data de manutenção preventiva declarada (`proxima_manutencao`).
   * **Objetivo:** Antecipar a vistoria técnica antes que o radar ou câmera pare de operar completamente.
3. **Recomendações Táticas de Despacho:**
   * O sistema cruza os bairros com índice de risco crítico e sugere ordens de movimentação de efetivo em tempo real (ex.: remanejar viaturas ou agentes para áreas com ativos inoperantes).

---

## 🎯 Conformidade com o Desafio

* **Fase 1 (Inventário Inteligente):** Cadastro de equipamentos com número de série único, endereçamento com coordenadas GPS, situações operacionais restritas, necessidade de manutenção e atributos flexíveis via coluna nativa `JSON`. Controle de acesso com separação de perfis (`admin` e `agente`) e trilha de auditoria vinculada à sessão.
* **Fase 2 (Mapa Operacional C.I.O.):** Dashboard com mapa interativo, marcação com cores de alerta por status, contadores de KPI em tempo real (disponibilidade, operacionais e efetivo em serviço) e filtros dinâmicos por tipo e situação.
* **Fase 3 (Previsões e Apoio à Decisão):** Painel executivo do PROMETHEUS contendo matriz de risco por região, probabilidade de quebra de equipamentos e orientações automáticas de despacho de segurança e trânsito.

---

## 📁 Estrutura de Diretórios

```text
FOGO/
├── api/
│   ├── telemetria.php          # Endpoint JSON de ativos, agentes e telemetria
│   └── preditivo.php           # Motor analítico, cálculo do IVOR e recomendações
├── assets/
│   ├── style.css               # Folha de estilos e identidade visual C.I.O.
│   └── dashboard.js            # Lógica Leaflet, camadas e eventos do mapa
├── database/
│   └── connection.php          # Conexão PDO com SQLite e verificação de integridade
├── includes/
│   └── auth.php                # Controle de autenticação e proteção RBAC
├── index.php                   # Hub central de comando, tabela filtrável e atalhos
├── mapa.php                    # Mapa operacional georreferenciado (Leaflet)
├── previsoes.php               # Painel analítico de apoio à decisão PROMETHEUS
├── login.php                   # Tela de autenticação e login
├── logout.php                  # Encerramento seguro de sessão
├── setup.php                   # Instalador e criador da base com dados de demonstração
└── README.md                   # Documentação oficial do projeto

## 🤖 Uso de Inteligência Artificial no Desenvolvimento

No desenvolvimento do projeto **F.O.G.O.**, utilizou-se o modelo **Gemini (Google)** como ferramenta de assistência à programação, ideação arquitetural e apoio ao código (pair programming), com atuação expressiva em:

* **Fase 2 (Centro de Operações & Georreferenciamento):**
  * Apoio na estruturação da integração do **Leaflet.js** com a camada aberta do OpenStreetMap.
  * Estruturação do endpoint de telemetria assíncrono em PHP (`api/telemetria.php`) e regras de renderização dinâmica de marcadores com filtros em JavaScript.

* **Fase 3 (Inteligência Preditiva & Apoio à Decisão):**
  * Colaboração na concepção algorítmica do **IVOR (Índice de Vulnerabilidade Operacional por Região)**, definindo pesos estatísticos para indisponibilidade, urgência de reparo e efetivo ativo.
  * Formulação da lógica preditiva de risco de pane preventiva em 7 dias (`api/preditivo.php`) e geração explicável das recomendações táticas de despacho para os gestores do PROMETHEUS.
