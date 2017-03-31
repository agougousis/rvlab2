<?php

/**
 * Builds the function description element for an R vLab function form
 *
 * @param string $function
 * @param array $tooltips
 * @return string
 */
function form_function_about($function, $tooltips)
{
    $teaser_length = 180;
    $html = "";
    if (!empty($tooltips[$function . '-about'])) {

        $about = $tooltips[$function . '-about'];
        if (!empty($tooltips[$function . '-function-title'])) {
            $about = "<strong>" . $tooltips[$function . '-function-title'] . ":</strong> " . $about;
        }
        if (strlen($about) > $teaser_length) {
            $teaser = substr($about, 0, $teaser_length);
            $html .= "<div id='$function-about-teaser' style='margin-top: 20px; color: gray'>$teaser... <a href='javascript:show_more(\"$function\")'>Read more</a></div>";
            $html .= "<div id='$function-about-all' style='margin-top: 20px; display: none'>$about</div>";
        } else {
            $html .= "<div id='$function-about-all' style='margin-top: 20px'>$about</div>";
        }
    }

    return $html;
}

/**
 * Builds a radio-buttons element for an R vLab function form
 *
 * @param string $tooltip_id
 * @param string $labelText
 * @param array $tooltips
 * @param array $workspace_files
 * @return string
 */
function form_radio_files($tooltip_id, $labelText, $tooltips, $workspace_files)
{
    If ($workspace_files->count() == 0) {
        return "<div style='color: red; margin: 20px;'>This function cannot work without files in your workspace!</div>";
    }

    $parts = explode('-', $tooltip_id);
    $input_id = $parts[1];

    $html = "<div class='radio_wrapper'>
        <div class='configuration-label'>
            <div class='row'>
                <div class='col-sm-11'>
                    $labelText
                </div>
                " . fTooltip($tooltip_id, $tooltips) . "
            </div>
        </div>";

    if (empty($workspace_files)) {
        $html .= "<br><span style='color: red'>No files in your workspace!</span>";
    }
    foreach ($workspace_files as $file) {
        $html .= "<div class='radio'>
                        <label>
                          <input type='radio' name='$input_id' value='" . $file->filename . "'>
                          " . $file->filename . "
                        </label>
                    </div>";
    }
    $html .= "</div>";
    return $html;
}

/**
 * Builds a list checkbox buttons to be used for file selection in an
 * R vLab function form
 *
 * @param string $tooltip_id
 * @param string $labelText
 * @param array $tooltips
 * @param array $workspace_files
 * @return string
 */
function form_checkbox_files($tooltip_id, $labelText, $tooltips, $workspace_files)
{
    $parts = explode('-', $tooltip_id);
    $input_id = $parts[1];

    $html = "<div class='radio_wrapper'>
        <div class='configuration-label'>
            <div class='row'>
                <div class='col-sm-11'>
                    $labelText
                </div>
                " . fTooltip($tooltip_id, $tooltips) . "
            </div>
        </div>";

    if (empty($workspace_files)) {
        $html .= "<br><span style='color: red'>No files in your workspace!</span>";
    }
    foreach ($workspace_files as $file) {
        $html .= "<div class='radio'>
                        <label>
                          <input type='checkbox' name='" . $input_id . "[]' value='" . $file->filename . "'>
                          " . $file->filename . "
                        </label>
                    </div>";
    }
    $html .= "</div>";
    return $html;
}

/**
 * Builds a dropdown element for an R vLab function form
 *
 * @param string $tooltip_id
 * @param string $labelText
 * @param array $options
 * @param string $default
 * @param array $tooltips
 * @return string
 */
function form_dropdown($tooltip_id, $labelText, $options, $default, $tooltips)
{
    $parts = explode('-', $tooltip_id);
    ;
    $input_id = $parts[1];

    $html = "<div class='select_wrapper'>
            <div class='row'>
                <div class='col-sm-11'>
                    <div class='configuration-label'>
                        $labelText
                    </div>
                    <select name='$input_id'>";
    foreach ($options as $option) {
        if ($option == $default) {
            $html .= "<option selected='selected'>$option</option>";
        } else {
            $html .= "<option>$option</option>";
        }
    }
    $html .= "</select>
                </div>
                " . fTooltip($tooltip_id, $tooltips) . "
            </div>
        </div>";

    return $html;
}

/**
 * Builds a text input element for an R vLab function form
 *
 * @param string $tooltip_id
 * @param string $labelText
 * @param string $default
 * @param array $tooltips
 * @return string
 */
function form_textinput($tooltip_id, $labelText, $default, $tooltips)
{
    $parts = explode('-', $tooltip_id);
    $input_id = $parts[1];

    $html = "<div class='textarea_wrapper'>
            <div class='row'>
                <div class='col-sm-11'>
                    <div class='configuration-label'>
                        $labelText
                    </div>
                    <input type='text' class='form-control' name='$input_id' value='$default'>
                </div>
                " . fTooltip($tooltip_id, $tooltips) . "
            </div>
        </div>";

    return $html;
}

/**
 * Builds a checkbox element for an R vLab function form
 *
 * @param string $tooltip_id
 * @param string $labelText
 * @param string $value
 * @param boolean $checked
 * @param array $tooltips
 * @return string
 */
function form_checkbox($tooltip_id, $labelText, $value, $checked, $tooltips)
{
    $parts = explode('-', $tooltip_id);
    $input_id = $parts[1];

    $html = "<div class='checkbox checkbox_wrapper'>
                <label>";
    if ($checked)
        $html .= "<input type='checkbox'  name='$input_id' value='$value' checked=''> $labelText";
    else
        $html .= "<input type='checkbox'  name='$input_id' value='$value'> $labelText";
    $html .= "</label>
            </div>";

    return $html;
}

/**
 * Builds a tooltip functionality for the informational image used in each
 * R vLab function form
 *
 * @param string $tooltip_id
 * @param array $tooltips
 * @return string
 */
function fTooltip($tooltip_id, $tooltips)
{
    $tooltipHtml = "<div class='col-sm-1'>";

    if (!empty($tooltips[$tooltip_id])) {
        $tooltipHtml .= "<img src='" . asset('images/info.png') . "' class='info-button' data-container='body' data-toggle='popover' data-placement='left' data-content='" . $tooltips[$tooltip_id] . "'>";
    }

    $tooltipHtml .= "</div>";

    return $tooltipHtml;
}