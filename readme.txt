=== SI5 Global para WooCommerce ===
Contributors: si5
Tags: woocommerce, ncm, cest, gtin, ean
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Completa os produtos da loja pelo código de barras: nome, foto, marca, NCM e CEST, direto do catálogo SI5 Global.

== Description ==

Digite o código de barras (GTIN/EAN) do produto e o plugin busca no catálogo [SI5 Global](https://global.si5.com.br/) — milhões de produtos com NCM e CEST.

* **Só preenche o que está vazio**: nunca troca o nome, a descrição ou a foto que a loja já cadastrou.
* **NCM e CEST** em campos próprios na aba Geral do produto; também podem ser copiados para os campos do seu plugin de nota fiscal.
* **Foto** baixada para a biblioteca de mídia quando o produto não tem imagem.
* **Marca** na taxonomia de marcas do WooCommerce.
* **Em massa**: ação "Completar com SI5 Global" na lista de produtos e varredura de todos os produtos sem NCM, em segundo plano.
* Lê o código do campo GTIN/EAN do WooCommerce, de campos de outros plugins ou do SKU (quando for um código válido).
* Respeita o limite do plano: se as consultas do dia acabarem, continua sozinho quando renovar.

NCM e CEST são sugestões do catálogo, com a origem registrada. Confirme com seu contador antes de emitir notas.

= Serviço externo =

O plugin consulta a API `https://global.si5.com.br/api/produtos/{código}` enviando apenas o código de barras do produto e o seu token. Termos e privacidade em https://global.si5.com.br/.

== Installation ==

1. Envie a pasta `si5-global-woocommerce` para `wp-content/plugins` (ou instale o .zip em Plugins › Adicionar novo).
2. Ative o plugin.
3. Crie um token em global.si5.com.br e informe em WooCommerce › SI5 Global.
4. Use "Testar conexão", depois "Completar agora" ou a ação em massa na lista de produtos.

== Frequently Asked Questions ==

= O plugin sobrescreve meus dados? =

Não. Nome, descrição e foto só são preenchidos quando estão vazios. NCM e CEST também, a não ser que você ligue a opção "Trocar NCM/CEST que já estiverem preenchidos" ou use o botão "Buscar dados agora" no produto.

= Quantas consultas posso fazer? =

O plano gratuito permite 100 consultas por dia. Planos maiores em global.si5.com.br.

== Changelog ==

= 1.0.1 =
* Códigos UPC-A (12 dígitos) agora são encontrados no catálogo.
* A consulta envia só o código de barras e o token (o endereço da loja saiu do user-agent).

= 1.0.0 =
* Primeira versão.
