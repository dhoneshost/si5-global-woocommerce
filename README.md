# SI5 Global para WooCommerce

Plugin WordPress que completa os produtos da loja pelo **código de barras (GTIN/EAN)**: nome, foto, marca, **NCM e CEST**, a partir do catálogo [SI5 Global](https://global.si5.com.br), com milhões de produtos do varejo brasileiro.

*English summary below.*

## O que faz

- **Só preenche o que está vazio.** Nome, descrição e foto que a loja já cadastrou nunca são trocados (o nome só é trocado quando é apenas o próprio código de barras).
- **NCM e CEST preenchidos** só são trocados com a opção "Trocar NCM/CEST que já estiverem preenchidos" ligada, inclusive pelo botão manual.
- **NCM e CEST** em campos próprios na aba *Geral* do produto, com opção de copiar para os campos do seu plugin de nota fiscal.
- **Foto** baixada para a biblioteca de mídia quando o produto não tem imagem.
- **Marca** na taxonomia de marcas do WooCommerce.
- **Em massa:** ação "Completar com SI5 Global" na lista de produtos e varredura em segundo plano (Action Scheduler) dos produtos sem NCM.
- Lê o código do campo GTIN/EAN do WooCommerce, de campos de outros plugins ou do SKU (quando é um código válido). Aceita EAN-8, EAN-13, UPC-A e GTIN-14.
- Respeita o limite do plano: se as consultas do dia acabarem, retoma sozinho quando o limite renovar.

NCM e CEST são **sugestões do catálogo**, com a origem registrada. Confirme com seu contador antes de emitir notas.

## Dados enviados

O plugin consulta `https://global.si5.com.br/api/produtos/{código}` enviando **apenas o código de barras do produto e o seu token**. As fotos são baixadas só dos servidores de imagem do SI5 Global, sem o token. Nenhum outro dado da loja (clientes, pedidos, preços, endereço do site) é enviado.

## Instalação

1. Baixe o `.zip` em [Releases](../../releases) (ou clone esta pasta para `wp-content/plugins/si5-global-woocommerce`).
2. Em *Plugins › Adicionar novo › Enviar plugin*, envie o `.zip` e ative.
3. Crie uma conta e um token em global.si5.com.br (*Minha conta*). O plano gratuito tem uma cota diária de consultas (veja os limites de cada plano em global.si5.com.br).
4. Em *WooCommerce › SI5 Global*, cole o token, use **Testar conexão** e depois **Completar agora** ou a ação em massa.

Requisitos: WordPress 6.4+, WooCommerce 8.0+, PHP 7.4+.

## English

WooCommerce plugin that fills product name, picture, brand and the Brazilian fiscal codes **NCM / CEST** from the barcode (GTIN/EAN), using the SI5 Global catalogue. It only fills empty fields, works in bulk in the background and respects the API plan limits. Only the barcode and your API token are sent to the API.

## Licença

[GPL-2.0-or-later](LICENSE). © 2026 SI5.
