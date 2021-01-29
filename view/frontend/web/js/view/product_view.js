require(['jquery', 'domReady!'], function ($) {
    var quantityDom = document.getElementsByClassName("qty");

    // Get the modal
    var modal = document.getElementById("myModal");
    // Get the button that opens the modal
    var btn = document.getElementById("myBtn");
    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];
    // When the user clicks on <span> (x), close the modal
    span.onclick = function () {
        modal.style.display = "none";
    }

    // When the user clicks on the button, show/open the modal
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        var quantity = quantityDom.qty.value;
        var price_one = $('.mobbex-finance').data('price');
         var tax_id = $('.mobbex-finance').data('cuit');
        //recalculate the price based on quantity
    var total_price = price_one * quantity;
    iframe.src = "https://mobbex.com/p/sources/widget/arg/"+tax_id+"/?total="+total_price;
            modal.style.display = "block";
            //set the modal and iframe style
            window.dispatchEvent(new Event('resize'));
            document.getElementById('iframe').style.width = "100%";
            document.getElementById('iframe').style.height = "96%";
            return false;
        });

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
});
