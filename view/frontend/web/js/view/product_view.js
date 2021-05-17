require(['jquery', 'domReady!'], function ($) {
    try{
        let quantityDom = document.getElementsByClassName("qty");

        // Get the modal
        let modal = document.getElementById("modal_mobbex");
        // Get the button that opens the modal
        let btn = document.getElementById("btn_mobbex_installments");
        // Get the <span> element that closes the modal
        let span = document.getElementsByClassName("button_mobbex_close")[0];
        // Get the <iframe> element that show the financing info, if it not exist then is null
        let iframe_element = document.getElementById("mobbex_iframe");
        
        // When the user clicks on <span> (x), close the modal
        if(span){
            span.onclick = function () {
                modal.style.display = "none";
            }
        }
        
        // When the user clicks on the button, show/open the modal, only if the button and the iframe are created
        if(btn && iframe_element){
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                let quantity = quantityDom.qty.value;
                let price_one = $('.mobbex-finance').data('price');
                let tax_id = $('.mobbex-finance').data('cuit');
                //recalculate the price based on quantity
                let total_price = price_one * quantity;
                mobbex_iframe.src = "https://mobbex.com/p/sources/widget/arg/"+tax_id+"/?total="+total_price;
                    modal.style.display = "block";
                    //set the modal and iframe style
                    window.dispatchEvent(new Event('resize'));
                    document.getElementById('mobbex_iframe').style.width = "100%";
                    document.getElementById('mobbex_iframe').style.height = "96%";
                    return false;
            });

            // When the user clicks anywhere outside of the modal, close it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        }
    }
    catch(error){
        console.log("Installment MOBBEX button failed "+error.message);
    }
});
