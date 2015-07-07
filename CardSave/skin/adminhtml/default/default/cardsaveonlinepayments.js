function CSV_runCrossReferenceTransaction(szActionURL, szOrderID, szCrossReference, szTransactionType)
{
    var szConfirmMessage;
    var szText;
    
    if (szActionURL == "")
    {
        alert("Error: Invalid action URL");
    }

    if (szTransactionType == "VOID")
    {
        szConfirmMessage = "Are you sure you would like to void this payment?";
        szText = "<ul class='messages'><li class='success-msg'><ul><li>Payment successfully voided</li></ul></li></ul>";
    }
    else if (szTransactionType == "COLLECTION")
    {
        szConfirmMessage = "Are you sure you would like to collect this authorized payment?";
        szText = "<ul class='messages'><li class='success-msg'><ul><li>Authorized payment successfully collected</li></ul></li></ul>";
    }
    else
    {
        alert("Error: Unknown transaction type to run for this action: " + szTransactionType);
        return;
    }

    if (confirm(szConfirmMessage))
    {
        new Ajax.Request(szActionURL,
                            { method: "post",
                                parameters: { OrderID: szOrderID, CrossReference: szCrossReference },
                                onSuccess: function (result) { var szMessage = result.responseText; if (szMessage == "0") { location.href = location.href; /* cheating with displaying a success message before the page is refreshed */ $('messages').update(szText); } else { alert(szMessage); } }
                            });
    }
}